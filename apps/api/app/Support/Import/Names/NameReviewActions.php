<?php

namespace App\Support\Import\Names;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\User;
use App\Support\Import\ProvenanceWriter;
use Illuminate\Support\Facades\DB;

/**
 * The one-click decisions on the name cleanup's review items
 * (import:clean-names): „Прифати предлог“ sets the proposed value, „Остави“
 * keeps the current one (the item stays dismissed while nothing changes),
 * „Спои ги“ merges a group of duplicate website drafts.
 */
final class NameReviewActions
{
    public function __construct(private readonly DoctorMerger $merger) {}

    public static function isNameItem(ImportReviewItem $item): bool
    {
        return $item->source === NameCleanup::SOURCE && $item->kind === ImportReviewKind::Uncertain
            && ($item->details['reason'] ?? null) === NameCleanup::REASON;
    }

    public static function hasSuggestion(ImportReviewItem $item): bool
    {
        return self::isNameItem($item) && ((is_string($item->details['suggestion'] ?? null) && $item->details['suggestion'] !== '')
            || (is_array($item->details['changes'] ?? null) && $item->details['changes'] !== []));
    }

    public static function isMergeGroup(ImportReviewItem $item): bool
    {
        return $item->source === NameCleanup::SOURCE && $item->kind === ImportReviewKind::Uncertain
            && ($item->details['reason'] ?? null) === DuplicateFinder::REASON
            && is_array($item->details['pairs'] ?? null) && $item->details['pairs'] !== [];
    }

    /**
     * Sets the suggestion, unless the field was locked or changed since the
     * item was raised (then staff decide on the profile itself).
     */
    public function accept(ImportReviewItem $item, User $by): bool
    {
        if (! self::hasSuggestion($item) || $item->status !== ImportReviewStatus::Open) {
            return false;
        }

        $changes = is_array($item->details['changes'] ?? null)
            ? $item->details['changes']
            : [[
                'subject_type' => $item->subject_type,
                'subject_id' => $item->subject_id,
                'field' => $item->details['field'] ?? null,
                'current' => $item->details['current'] ?? null,
                'suggestion' => $item->details['suggestion'] ?? null,
            ]];
        $problem = (string) ($item->details['problem'] ?? '');

        return DB::transaction(function () use ($item, $changes, $problem, $by): bool {
            if (! $item->resolve(ImportReviewStatus::Resolved, 'accepted_suggestion', $by)) {
                return false;
            }

            $applied = 0;

            foreach ($changes as $change) {
                $applied += is_array($change) && $this->set($change, $problem, $by) ? 1 : 0;
            }

            // Nothing applied (everything changed since): staff decide on
            // the profiles; the item stays open.
            if ($applied === 0) {
                ImportReviewItem::query()->whereKey($item->getKey())->update(['status' => ImportReviewStatus::Open->value, 'resolution' => null, 'resolved_by_id' => null, 'resolved_at' => null]);
                $item->refresh();

                return false;
            }

            return true;
        });
    }

    /**
     * Sets one proposed value, unless the field was locked or changed since
     * the item was raised.
     *
     * @param  array<string, mixed>  $change
     */
    private function set(array $change, string $problem, User $by): bool
    {
        $field = (string) ($change['field'] ?? '');
        $suggestion = $change['suggestion'] ?? null;
        $subject = match ($change['subject_type'] ?? null) {
            FieldProvenance::SUBJECT_DOCTOR => Doctor::query()->find($change['subject_id'] ?? 0),
            FieldProvenance::SUBJECT_FACILITY => Facility::query()->find($change['subject_id'] ?? 0),
            default => null,
        };

        if ($subject === null || ! in_array($field, ['full_name', 'title', 'name'], true) || ! is_string($suggestion) || $suggestion === '') {
            return false;
        }

        $type = ImportReviewItem::subjectTypeOf($subject);
        $provenance = FieldProvenance::query()->where('subject_type', $type)->where('subject_id', $subject->getKey())->where('field', $field)->first();
        $current = ProvenanceWriter::normalise($subject->getAttribute($field));

        if ($provenance?->locked || $current !== ProvenanceWriter::normalise($change['current'] ?? null)) {
            return false;
        }

        $subject->setAttribute($field, $suggestion);
        activity()->withoutLogging(fn () => $subject->save());

        // Staff chose it: like a staff edit, the next import does not
        // overwrite it (a conflict shows the source's value instead).
        FieldProvenance::query()->updateOrCreate(
            ['subject_type' => $type, 'subject_id' => $subject->getKey(), 'field' => $field],
            ['source' => 'staff', 'value' => $suggestion, 'observed_at' => now(), 'source_record_id' => $provenance?->source_record_id],
        );

        NameCleanup::log($subject, $field, $current, $suggestion, 'accepted_suggestion:'.$problem, $by);

        return true;
    }

    public function keep(ImportReviewItem $item, User $by): bool
    {
        return $item->resolve(ImportReviewStatus::Dismissed, 'kept', $by);
    }

    /**
     * Merges every pair of the group still mergeable.
     *
     * @return array{merged: int, skipped: int}
     */
    public function merge(ImportReviewItem $item, User $by): array
    {
        if (! self::isMergeGroup($item) || $item->status !== ImportReviewStatus::Open) {
            return ['merged' => 0, 'skipped' => 0];
        }

        $merged = 0;
        $skipped = 0;

        foreach ((array) $item->details['pairs'] as $pair) {
            if (is_array($pair) && count($pair) === 2 && $this->merger->merge((int) $pair[0], (int) $pair[1], $by)) {
                $merged++;
            } else {
                $skipped++;
            }
        }

        // Nothing could be merged (every draft changed since): the group
        // stays open for staff to look at, not closed as „merged“.
        if ($merged > 0) {
            $item->resolve(ImportReviewStatus::Resolved, 'merged', $by);
        }

        return ['merged' => $merged, 'skipped' => $skipped];
    }
}
