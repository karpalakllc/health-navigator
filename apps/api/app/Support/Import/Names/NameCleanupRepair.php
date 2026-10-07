<?php

namespace App\Support\Import\Names;

use App\Enums\ImportReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Support\Import\ImportAlreadyRunning;
use App\Support\Import\NameKey;
use App\Support\Import\ProvenanceWriter;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * import:repair-name-cleanup — a one-off repair of what an earlier
 * import:clean-names wrote with rules since fixed (wave 8 review):
 *
 * - `b1`: a facility name whose first word, or the first word of a quoted
 *   name, was lower-cased („ПЗУ „до Дент““);
 * - `s2`: a Latin s/S, j/J or Y inside a Cyrillic word folded to ѕ/Ѕ, ј/Ј, У
 *   (now left for a person: the original value comes back, with an item);
 * - `s3`: a name the rules were uncertain about yet partly rewrote („Ана
 *   Петрова-Специјалист По Педијатрија“), a re-cased title word, a Latin
 *   academic abbreviation („Mr. sc.“) — recomputed, or the original value
 *   back with an item.
 *
 * Each automatic change in the activity log (log `name_cleanup`, old → new)
 * is recomputed with today's rules from its ORIGINAL value. Only fields the
 * cleanup still owns are touched (provenance „cleanup“ with the value it
 * wrote; never locked, staff-set or since-imported values). Any other
 * difference (a town or rule the repair is not about) is only counted. Each
 * repair is logged (`name_cleanup`, category `repair:…`) and its provenance
 * kept; open name-cleanup review items get today's proposals (a proposal
 * still holding a Latin letter is dropped, its name gets an item of its
 * own). Running it again changes nothing.
 */
final class NameCleanupRepair
{
    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<string, true> */
    private array $licenceKeys = [];

    private bool $apply = false;

    private ?ImportRun $run = null;

    public function run(bool $apply): ImportRun
    {
        $lock = ImportAlreadyRunning::lock(NameCleanup::SOURCE);

        try {
            $this->apply = $apply;
            $this->counts = [];
            $this->run = ImportRun::start(NameCleanup::SOURCE, ! $apply);

            try {
                $this->licenceKeys = [];
                DB::table('komora_licences')->select('full_name')->orderBy('id')->chunk(2000, function ($rows): void {
                    foreach ($rows as $row) {
                        $this->licenceKeys[NameKey::sorted((string) $row->full_name)] = true;
                    }
                });

                foreach ($this->groups() as $subjects) {
                    $this->transaction(fn () => $this->repairSubject($subjects));
                }

                $this->transaction(fn () => $this->refreshItems());
            } catch (Throwable $exception) {
                $this->run->forceFill(['counts' => $this->sortedCounts()]);
                $this->run->fail($exception);
                report($exception);

                return $this->run;
            }

            $this->run->finish($this->sortedCounts());

            return $this->run;
        } finally {
            $lock->release();
        }
    }

    /**
     * The automatic cleanup chain of every field, by subject: the original
     * value (the first entry's old), the value the chain left (the last
     * entry's new) and the categories applied. A field a person decided on
     * („Прифати предлог“, any causer) is left out.
     *
     * @return array<string, array<string, array{type: string, id: int, field: string, original: string|null, last: string|null, categories: list<string>}>>
     */
    private function groups(): array
    {
        $types = [(new Doctor)->getMorphClass() => 'doctor', (new Facility)->getMorphClass() => 'facility'];
        $fields = [];
        $decided = [];

        Activity::query()->where('log_name', NameCleanup::LOG)->where('event', 'name_cleanup')->orderBy('id')
            ->each(function (Activity $entry) use ($types, &$fields, &$decided): void {
                $type = $types[(string) $entry->subject_type] ?? null;
                $field = (string) ($entry->properties['field'] ?? '');
                $category = (string) ($entry->properties['category'] ?? '');

                if ($type === null || $field === '') {
                    return;
                }

                $key = $type.':'.$entry->subject_id;

                if ($entry->causer_id !== null || str_starts_with($category, 'accepted_suggestion')) {
                    $decided[$key.':'.$field] = true;

                    return;
                }

                $fields[$key][$field] ??= ['type' => $type, 'id' => (int) $entry->subject_id, 'field' => $field, 'original' => $entry->properties['old'] ?? null, 'last' => null, 'categories' => []];
                $fields[$key][$field]['last'] = $entry->properties['new'] ?? null;

                if (! str_starts_with($category, 'repair:')) {
                    array_push($fields[$key][$field]['categories'], ...explode('+', $category));
                }
            }, 500);

        foreach ($fields as $key => $byField) {
            foreach (array_keys($byField) as $field) {
                if (isset($decided[$key.':'.$field])) {
                    $this->count('skipped_decided_by_staff');
                    unset($fields[$key][$field]);
                }
            }
        }

        return array_filter($fields);
    }

    /**
     * @param  array<string, array{type: string, id: int, field: string, original: string|null, last: string|null, categories: list<string>}>  $fields
     */
    private function repairSubject(array $fields): void
    {
        $first = reset($fields);
        $subject = $first['type'] === 'doctor' ? Doctor::query()->find($first['id']) : Facility::query()->find($first['id']);

        if ($subject === null) {
            $this->count('skipped_profile_gone', count($fields));

            return;
        }

        $nameRepairable = false;

        // The name before the title: a title taken out of the name follows it.
        foreach (['full_name', 'name', 'title'] as $field) {
            if (! isset($fields[$field])) {
                continue;
            }

            $group = $fields[$field];
            $state = $this->state($subject, $field, $group['last']);
            $this->count($group['type'].'.'.$field.'.checked');

            if ($state !== 'ok') {
                $this->count($group['type'].'.'.$field.'.skipped_'.$state);

                continue;
            }

            if ($field === 'full_name') {
                $nameRepairable = true;
            }

            $result = match ($field) {
                'name' => $this->facilityName($group, $subject),
                'full_name' => $this->doctorName($group),
                default => $this->doctorTitle($group, $fields['full_name'] ?? null, $nameRepairable),
            };

            $this->settle($subject, $field, $group['last'], $result);
        }
    }

    /**
     * @param  array{value: string|null, class: string, problem?: string|null, suggestion?: string|null}  $result
     */
    private function settle(Doctor|Facility $subject, string $field, ?string $current, array $result): void
    {
        $type = ImportReviewItem::subjectTypeOf($subject);
        $value = $result['value'];

        if (ProvenanceWriter::normalise($value) === ProvenanceWriter::normalise($current)) {
            $this->count($type.'.'.$field.'.unchanged');

            return;
        }

        // A difference this repair is not about (a town, another rule): left as it is.
        if ($result['class'] === 'other') {
            $this->count($type.'.'.$field.'.other_difference_left');

            return;
        }

        $this->count($type.'.'.$field.'.repaired_'.$result['class']);

        if (! $this->apply) {
            return;
        }

        $subject->setAttribute($field, $value);
        activity()->withoutLogging(fn () => $subject->save());
        FieldProvenance::query()->where('subject_type', $type)->where('subject_id', $subject->getKey())->where('field', $field)
            ->update(['value' => $value, 'observed_at' => now()]);
        NameCleanup::log($subject, $field, $current, $value, 'repair:'.$result['class'], null);

        if (($result['problem'] ?? null) !== null && $value !== null) {
            $item = NameCleanup::raiseItem($subject, $field, $value, $result['suggestion'] ?? null, (string) $result['problem'], (int) $this->run?->getKey());
            $this->count($item->wasRecentlyCreated ? 'review_items_created' : 'review_items_updated');
        }
    }

    /**
     * 'ok' when the cleanup still owns the field and its value is the one
     * the cleanup chain left; else 'locked', 'staff' or 'changed'.
     */
    private function state(Doctor|Facility $subject, string $field, ?string $last): string
    {
        $provenance = FieldProvenance::query()->where('subject_type', ImportReviewItem::subjectTypeOf($subject))
            ->where('subject_id', $subject->getKey())->where('field', $field)->first();
        $current = ProvenanceWriter::normalise($subject->getAttribute($field));

        if ($provenance?->locked) {
            return 'locked';
        }

        if ($provenance === null || $provenance->source !== NameCleanup::SOURCE || ProvenanceWriter::normalise($provenance->value) !== $current) {
            return 'staff';
        }

        return $current === ProvenanceWriter::normalise($last) ? 'ok' : 'changed';
    }

    /**
     * @param  array{original: string|null, last: string|null, categories: list<string>}  $group
     * @return array{value: string|null, class: string, problem?: string|null, suggestion?: string|null}
     */
    private function facilityName(array $group, Doctor|Facility $facility): array
    {
        $original = (string) $group['original'];
        $cleaned = FacilityName::clean($original, $facility instanceof Facility ? $facility->city : null);

        if ($cleaned->uncertain !== null) {
            return ['value' => $original, 'class' => 's2', 'problem' => $cleaned->uncertain, 'suggestion' => null];
        }

        $value = $cleaned->value !== '' ? $cleaned->value : $original;

        return ['value' => $value, 'class' => mb_strtolower($value, 'UTF-8') === mb_strtolower((string) $group['last'], 'UTF-8') ? 'b1' : 'other'];
    }

    /**
     * @param  array{original: string|null, last: string|null, categories: list<string>}  $group
     * @return array{value: string|null, class: string, problem?: string|null, suggestion?: string|null}
     */
    private function doctorName(array $group): array
    {
        $original = (string) $group['original'];
        $cleaned = PersonName::clean($original);
        $glyph = in_array('homoglyph', $group['categories'], true) && $cleaned->uncertain === 'mixed_script';

        if ($cleaned->uncertain !== null && $cleaned->uncertain !== 'latin_script') {
            return ['value' => $original, 'class' => $glyph ? 's2' : 's3', 'problem' => $cleaned->uncertain, 'suggestion' => $cleaned->suggestion];
        }

        $value = $cleaned->value;
        $words = explode(' ', $value);

        // The surname-first fix the cleanup made (directory evidence) stays.
        if (in_array('order', $group['categories'], true) && count($words) === 2) {
            $value = $words[1].' '.$words[0];
        }

        if (NameKey::sorted($value) !== NameKey::sorted($original)
            && isset($this->licenceKeys[NameKey::sorted($original)]) && ! isset($this->licenceKeys[NameKey::sorted($value)])) {
            return ['value' => $original, 'class' => 's3', 'problem' => 'licence_name_differs', 'suggestion' => $value];
        }

        $last = (string) $group['last'];
        $class = match (true) {
            mb_strtolower($value, 'UTF-8') === mb_strtolower($last, 'UTF-8') => 's3',
            PersonName::holdsNonNameWord($last) => 's3',
            default => 'other',
        };

        return ['value' => $value, 'class' => $class];
    }

    /**
     * @param  array{original: string|null, last: string|null, categories: list<string>}  $group
     * @param  array{original: string|null, last: string|null, categories: list<string>}|null  $name
     * @return array{value: string|null, class: string, problem?: string|null, suggestion?: string|null}
     */
    private function doctorTitle(array $group, ?array $name, bool $nameRepairable): array
    {
        // A title taken out of the name: it follows the repaired name.
        if ($group['original'] === null) {
            if ($name === null || ! $nameRepairable) {
                return ['value' => $group['last'], 'class' => 'other'];
            }

            $cleaned = PersonName::clean((string) $name['original']);

            return ['value' => $cleaned->uncertain !== null && $cleaned->uncertain !== 'latin_script' ? null : $cleaned->title, 'class' => 's3'];
        }

        $cleaned = DoctorTitle::clean($group['original']);
        $value = $cleaned === null ? null : ($cleaned->uncertain !== null ? $group['original'] : ($cleaned->value !== '' ? $cleaned->value : null));
        $latin = preg_match('/(?<![\p{L}])(?:sc|med|mag|univ|subspec|docent|profesor|asist)(?![\p{L}])/iu', $group['original']) === 1;

        return ['value' => $value, 'class' => $latin ? 's3' : 'other'];
    }

    /**
     * Open name-cleanup items get today's proposals: the Latin-script batch
     * drops a proposal that still holds a Latin letter (that name gets an
     * item of its own, for a manual edit).
     */
    private function refreshItems(): void
    {
        $items = ImportReviewItem::query()->open()->where('source', NameCleanup::SOURCE)->get()
            ->filter(fn (ImportReviewItem $item): bool => NameReviewActions::isNameItem($item));

        foreach ($items as $item) {
            $changes = $item->details['changes'] ?? null;

            if (! is_array($changes)) {
                continue;
            }

            $kept = [];
            $alone = [];
            $changed = 0;

            foreach ($changes as $change) {
                $proposal = PersonName::clean((string) ($change['current'] ?? ''))->suggestion;

                if ($proposal === null || preg_match('/\p{Latin}/u', $proposal) === 1) {
                    $alone[] = $change;
                } else {
                    $changed += $proposal !== ($change['suggestion'] ?? null) ? 1 : 0;
                    $kept[] = array_merge($change, ['suggestion' => $proposal]);
                }
            }

            if ($changed === 0 && $alone === []) {
                continue;
            }

            $this->count('batch_proposals_changed', $changed);
            $this->count('batch_proposals_split_out', count($alone));

            if (! $this->apply) {
                continue;
            }

            $problem = (string) ($item->details['problem'] ?? 'latin_script');

            if ($kept === []) {
                $item->resolve(ImportReviewStatus::Dismissed, 'repaired');
            } else {
                NameCleanup::raiseBatch($problem, $kept, (int) $this->run?->getKey());
            }

            foreach ($alone as $change) {
                $subject = ($change['subject_type'] ?? null) === FieldProvenance::SUBJECT_DOCTOR ? Doctor::query()->find($change['subject_id'] ?? 0) : null;

                if ($subject !== null) {
                    NameCleanup::raiseItem($subject, (string) $change['field'], (string) $change['current'], null, $problem, (int) $this->run?->getKey());
                    $this->count('review_items_created');
                }
            }
        }
    }

    private function transaction(callable $work): void
    {
        $this->apply ? DB::transaction($work) : $work();
    }

    private function count(string $key, int $by = 1): void
    {
        if ($by !== 0) {
            $this->counts[$key] = ($this->counts[$key] ?? 0) + $by;
        }
    }

    /**
     * @return array<string, int>
     */
    private function sortedCounts(): array
    {
        ksort($this->counts);

        return $this->counts;
    }
}
