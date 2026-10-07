<?php

namespace App\Support\Import\Names;

use App\Enums\ImportReviewStatus;
use App\Models\Doctor;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\SourceRecord;
use App\Models\User;
use App\Support\Import\ImportBookkeeping;
use App\Support\Import\ImportSuppressions;
use Illuminate\Support\Facades\DB;

/**
 * Merges a hidden website draft into the profile of the same person (a
 * staff decision from the review queue, never automatic):
 *
 * - the draft's workplaces join the profile (source and department kept);
 * - its specialties too, only when the profile has none (a ФЗОМ contract
 *   says what the doctor is; a staff page adding a specialty could change
 *   which licence fits);
 * - its title, when the profile has none;
 * - its website source records now point at the profile, so the next
 *   import of that site updates the profile instead of re-creating the
 *   draft, and the verification engine reads the staff page as the
 *   profile's evidence;
 * - the draft is deleted without a suppression (it is not a removal: the
 *   person stays in the directory).
 *
 * Refused when the draft is no longer a mergeable draft (published, owned,
 * reviewed, licensed, suppressed) or either profile is gone.
 */
final class DoctorMerger
{
    public function merge(int $fromId, int $intoId, ?User $by): bool
    {
        if ($fromId === $intoId) {
            return false;
        }

        $merged = DB::transaction(function () use ($fromId, $intoId, $by): bool {
            $from = Doctor::query()->lockForUpdate()->find($fromId);
            $into = Doctor::query()->lockForUpdate()->find($intoId);

            if ($from === null || $into === null || ! DuplicateFinder::mergeableDraft((object) $from->getAttributes())
                || ImportSuppressions::isSuppressed($from) || ImportSuppressions::isSuppressed($into)
                || DB::table('doctor_claim_requests')->where('doctor_id', $fromId)->exists()
                || DB::table('doctor_change_requests')->where('doctor_id', $fromId)->exists()) {
                return false;
            }

            $now = now();
            $intoFacilities = DB::table('doctor_facility')->where('doctor_id', $intoId)->pluck('facility_id')->map(fn ($id): int => (int) $id)->all();
            $movedFacilities = [];

            foreach (DB::table('doctor_facility')->where('doctor_id', $fromId)->get() as $link) {
                if (! in_array((int) $link->facility_id, $intoFacilities, true)) {
                    DB::table('doctor_facility')->insert([
                        'doctor_id' => $intoId,
                        'facility_id' => $link->facility_id,
                        'is_primary' => $intoFacilities === [] && $movedFacilities === [],
                        'source' => $link->source,
                        'work_unit' => $link->work_unit,
                        'contract_type' => $link->contract_type,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $movedFacilities[] = (int) $link->facility_id;
                }
            }

            $movedSpecialties = [];

            if (! DB::table('doctor_specialty')->where('doctor_id', $intoId)->exists()) {
                foreach (DB::table('doctor_specialty')->where('doctor_id', $fromId)->orderByDesc('is_primary')->get() as $index => $link) {
                    DB::table('doctor_specialty')->insert([
                        'doctor_id' => $intoId,
                        'specialty_id' => $link->specialty_id,
                        'is_primary' => $index === 0,
                        'source' => $link->source,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $movedSpecialties[] = (int) $link->specialty_id;
                }
            }

            if ($into->title === null && $from->title !== null) {
                $into->title = $from->title;
                activity()->withoutLogging(fn () => $into->save());
                $provenance = FieldProvenance::query()->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)->where('subject_id', $fromId)->where('field', 'title')->first();

                if ($provenance !== null) {
                    FieldProvenance::query()->updateOrCreate(
                        ['subject_type' => FieldProvenance::SUBJECT_DOCTOR, 'subject_id' => $intoId, 'field' => 'title'],
                        $provenance->only(['source', 'source_record_id', 'source_url', 'value', 'observed_at']),
                    );
                }
            }

            SourceRecord::query()->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)->where('subject_id', $fromId)
                ->update(['subject_id' => $intoId, 'updated_at' => $now]);
            DB::table('komora_licences')->where('doctor_id', $fromId)->update(['doctor_id' => null]);

            // Its open items (the "new" draft item…) are settled by the merge.
            ImportReviewItem::query()->open()->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)->where('subject_id', $fromId)
                ->get()->each(fn (ImportReviewItem $item) => $item->resolve(ImportReviewStatus::Resolved, 'merged', $by));

            // Not a removal: no suppression, so no deleting events.
            Doctor::withoutEvents(fn () => $from->forceDelete());
            ImportBookkeeping::forget(FieldProvenance::SUBJECT_DOCTOR, $fromId);

            $entry = activity(NameCleanup::LOG)
                ->performedOn($into)
                ->event('merged')
                ->withProperties(['merged_doctor_id' => $fromId, 'facilities_added' => $movedFacilities, 'specialties_added' => $movedSpecialties]);

            if ($by !== null) {
                $entry->causedBy($by);
            }

            $entry->log('profiles_merged');

            return true;
        });

        // Workplaces and specialties were written to the pivots directly:
        // the kept profile's search entry is refreshed (after the commit).
        if ($merged) {
            Doctor::query()->find($intoId)?->searchable();
        }

        return $merged;
    }
}
