<?php

namespace App\Support\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * What staff can do with an import review item. Every change to a profile
 * goes through the model (search index, caches and the activity log see it
 * like any staff edit); the item records who resolved it and how.
 */
final class ImportReviewActions
{
    /** Fields a conflict may carry; anything else is refused. */
    private const WRITABLE_FIELDS = [
        'doctor' => ['full_name', 'city', 'title'],
        'facility' => ['name', 'city', 'address', 'type', 'ownership', 'phone', 'email', 'website'],
    ];

    /**
     * Publishes the draft behind a "new" item (and the imported specialties
     * it uses, which were created hidden). Returns false when there is
     * nothing to publish.
     */
    public function publish(ImportReviewItem $item, User $by): bool
    {
        $subject = $item->subject();

        if ($subject === null || $subject->trashed()) {
            return false;
        }

        DB::transaction(function () use ($subject, $item, $by): void {
            if (! $subject->is_published) {
                $subject->forceFill(['is_published' => true, 'published_at' => $subject->published_at ?? now()])->save();
            }

            if ($subject instanceof Doctor) {
                Specialty::query()
                    ->where('created_by_import', true)
                    ->where('is_published', false)
                    ->whereIn('id', $subject->specialties()->pluck('specialties.id'))
                    ->get()
                    ->each(fn (Specialty $specialty) => $specialty->forceFill(['is_published' => true])->save());
            }

            $this->closeAllNew($item, $by, 'published');
        });

        return true;
    }

    /**
     * Conflict: take the value the source has.
     */
    public function acceptIncoming(ImportReviewItem $item, User $by): bool
    {
        $subject = $item->subject();
        $field = (string) ($item->details['field'] ?? '');

        if ($item->kind !== ImportReviewKind::Conflict || $subject === null
            || ! in_array($field, self::WRITABLE_FIELDS[$item->subject_type] ?? [], true)) {
            return false;
        }

        $incoming = $item->details['incoming'] ?? null;

        DB::transaction(function () use ($subject, $item, $field, $incoming, $by): void {
            $subject->setAttribute($field, $incoming);
            $subject->save();

            // The value is the source's again: later imports may update it.
            FieldProvenance::query()->updateOrCreate(
                ['subject_type' => $item->subject_type, 'subject_id' => $subject->getKey(), 'field' => $field],
                ['source' => $item->source, 'value' => $incoming, 'observed_at' => now()],
            );

            $item->resolve(ImportReviewStatus::Resolved, 'accepted_incoming', $by);
        });

        return true;
    }

    /**
     * Conflict: keep the current value. The field is locked, so the import
     * stops proposing the same change every run; unlock it on the profile.
     */
    public function keepCurrent(ImportReviewItem $item, User $by): bool
    {
        $subject = $item->subject();
        $field = (string) ($item->details['field'] ?? '');

        if ($item->kind !== ImportReviewKind::Conflict || $subject === null || $field === '') {
            return false;
        }

        ProvenanceWriter::setLock($subject, $field, true, $by);
        $item->resolve(ImportReviewStatus::Resolved, 'kept_current_and_locked', $by);

        return true;
    }

    /**
     * Missing from the source: hide the profile (reviews are kept).
     */
    public function hide(ImportReviewItem $item, User $by): bool
    {
        $subject = $item->subject();

        if ($subject === null) {
            return false;
        }

        if ($subject->is_published) {
            $subject->forceFill(['is_published' => false])->save();
        }

        $item->resolve(ImportReviewStatus::Resolved, 'hidden', $by);

        return true;
    }

    public function dismiss(ImportReviewItem $item, User $by): void
    {
        $item->resolve(ImportReviewStatus::Dismissed, 'dismissed', $by);
    }

    private function closeAllNew(ImportReviewItem $item, User $by, string $resolution): void
    {
        ImportReviewItem::query()->open()
            ->where('kind', ImportReviewKind::New)
            ->where('subject_type', $item->subject_type)
            ->where('subject_id', $item->subject_id)
            ->get()
            ->each(fn (ImportReviewItem $open) => $open->resolve(ImportReviewStatus::Resolved, $resolution, $by));

        if ($item->status === ImportReviewStatus::Open) {
            $item->resolve(ImportReviewStatus::Resolved, $resolution, $by);
        }
    }

    /**
     * @return list<string>
     */
    public static function lockableFields(Doctor|Facility $subject): array
    {
        return $subject instanceof Doctor
            ? ['full_name', 'city', 'title', 'specialties', 'facilities', EloquentDoctorLicenceSink::FIELD]
            : ['name', 'city', 'address', 'type', 'ownership', 'phone', 'email', 'website', 'avatar_url', 'cover_path'];
    }
}
