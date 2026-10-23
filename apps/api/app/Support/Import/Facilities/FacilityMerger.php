<?php

namespace App\Support\Import\Facilities;

use App\Enums\FacilityType;
use App\Enums\ImportReviewStatus;
use App\Models\Facility;
use App\Models\FacilityMedia;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ProfileCorrection;
use App\Models\ReviewReminder;
use App\Models\SourceRecord;
use App\Models\User;
use App\Support\Import\Website\InstitutionsJsonImporter;
use App\Support\Verification\Engine\EvidenceLoader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges a website-only facility (a draft the website import created) into
 * the register facility it duplicates — a staff decision from the admin, or
 * a high-confidence proposal of FacilityDuplicateMatcher run by hand.
 * One transaction; nothing is deleted for good:
 *
 * - doctors' workplace links move to the register facility (department,
 *   source and primary flag kept; a doctor already linked there keeps that
 *   link, which becomes primary if the moved one was, and gets the
 *   department if it had none). A doctor whose workplaces staff locked
 *   keeps the old link (counted; staff decide on the profile);
 * - website fields the register facility lacks (phone, e-mail, website,
 *   logo, cover, description, office hours, map position) are copied with
 *   their provenance (source `website`, page URL), so the next import of the
 *   site treats them as its own values — never a field locked, nor one
 *   with a value;
 * - the website images (facility_media) move, so „Website images“ lists
 *   them on the register facility; departments move;
 * - the website's source records point at the register facility, and the
 *   staff-page entries are re-keyed to it (`worker:<id>:…`): the next import
 *   of the site updates the register facility instead of re-creating the
 *   draft, and the verification engine reads the staff page as evidence for
 *   the register facility's doctors. A „trust this website“ decision moves
 *   with them;
 * - the website facility is soft-deleted with `merged_into_id` (restorable;
 *   its own provenance rows stay with it), its open review items are closed
 *   („merged“), and one activity-log entry on each facility records it.
 *
 * Idempotent: merging again into the same facility reports
 * `already_merged` and changes nothing. Refused (nothing written) when the
 * source is not a website-only facility, the target is not a register
 * facility, either is a pharmacy, or the source has reviews or correction
 * requests (those are people's contributions: merge by hand).
 */
final class FacilityMerger
{
    public const LOG = 'Facility merge';

    public const RESOLUTION = 'merged';

    /** Facility fields a merge may fill on the register facility. */
    public const FIELDS = ['phone', 'email', 'website', 'avatar_url', 'cover_path', 'description', 'office_hours', 'latitude', 'longitude'];

    private static ?bool $hasMergeColumn = null;

    /**
     * Whether facilities.merged_into_id exists (the migration of
     * 2026-10-23). The matcher works without it (a read-only dry run on a
     * database not migrated yet); a merge does not.
     */
    public static function hasMergeColumn(): bool
    {
        return self::$hasMergeColumn ??= Schema::hasColumn('facilities', 'merged_into_id');
    }

    public static function forgetSchema(): void
    {
        self::$hasMergeColumn = null;
    }

    /**
     * Why the merge cannot be done, or null when it can (or was done already).
     */
    public function refusal(?Facility $from, ?Facility $into): ?string
    {
        return match (true) {
            $from === null => 'source_missing',
            $into === null => 'target_missing',
            (int) $from->getKey() === (int) $into->getKey() => 'same_facility',
            $from->trashed() && (int) $from->getAttribute('merged_into_id') === (int) $into->getKey() => null,
            $from->trashed() && $from->getAttribute('merged_into_id') !== null => 'merged_elsewhere',
            $from->trashed() => 'source_deleted',
            $into->trashed() || $into->getAttribute('merged_into_id') !== null => 'target_deleted',
            $from->type === FacilityType::Pharmacy || $into->type === FacilityType::Pharmacy => 'pharmacy',
            ! self::isWebsiteOnly($from) => 'source_not_website_only',
            $into->import_source === InstitutionsJsonImporter::SOURCE && self::isWebsiteOnly($into) => 'target_not_register',
            $from->reviews()->exists() || ReviewReminder::query()->where('reviewable_type', Facility::class)->where('reviewable_id', $from->getKey())->exists() => 'source_has_reviews',
            ProfileCorrection::query()->where('subject_type', Facility::class)->where('subject_id', $from->getKey())->exists() => 'source_has_corrections',
            default => null,
        };
    }

    /**
     * A facility only the website import knows: created by it, and no
     * other source (ФЗОМ, the pharmacy register) has a record of it.
     */
    public static function isWebsiteOnly(Facility $facility): bool
    {
        return $facility->import_source === InstitutionsJsonImporter::SOURCE
            && ! SourceRecord::query()
                ->where('subject_type', FieldProvenance::SUBJECT_FACILITY)
                ->where('subject_id', $facility->getKey())
                ->where('source', '!=', InstitutionsJsonImporter::SOURCE)
                ->exists();
    }

    public function merge(int $fromId, int $intoId, ?User $by, string $basis = 'staff'): FacilityMergeResult
    {
        if (! self::hasMergeColumn()) {
            return FacilityMergeResult::refused('migration_missing');
        }

        $result = DB::transaction(function () use ($fromId, $intoId, $by, $basis): FacilityMergeResult {
            // Both rows locked, the lower id first (two merges never deadlock).
            $rows = Facility::withTrashed()->whereKey([$fromId, $intoId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $from = $rows->get($fromId);
            $into = $rows->get($intoId);
            $refusal = $this->refusal($from, $into);

            if ($refusal !== null) {
                return FacilityMergeResult::refused($refusal);
            }

            /** @var Facility $from */
            /** @var Facility $into */
            if ($from->trashed()) {
                return FacilityMergeResult::alreadyMerged();
            }

            $counts = [];
            $doctorIds = $this->moveDoctors($fromId, $intoId, $counts);
            $fields = $this->copyFields($from, $into, $counts);
            $this->moveMedia($from, $into, $counts);
            $this->moveDepartments($fromId, $intoId, $counts);
            $this->moveSourceRecords($fromId, $intoId, $counts);
            $this->moveSiteTrust($fromId, $intoId);

            $now = now();
            ImportReviewItem::query()->open()
                ->where('subject_type', FieldProvenance::SUBJECT_FACILITY)
                ->where('subject_id', $fromId)
                ->get()
                ->each(fn (ImportReviewItem $item) => $item->resolve(ImportReviewStatus::Resolved, self::RESOLUTION, $by));

            // The images now belong to the register facility's rows: the
            // draft must not delete those files if it is ever force-deleted.
            DB::table('facilities')->where('id', $fromId)->update(['avatar_url' => null, 'cover_path' => null, 'merged_into_id' => $intoId, 'updated_at' => $now]);
            $from->refresh();
            $from->delete();

            $properties = [
                'merged_facility_id' => $fromId,
                'merged_facility_name' => $from->name,
                'into_facility_id' => $intoId,
                'basis' => $basis,
                'fields_copied' => $fields,
                'doctor_ids' => $doctorIds,
                'counts' => $counts,
            ];

            foreach ([[$into, 'facility_merged'], [$from, 'facility_merged_away']] as [$subject, $description]) {
                $entry = activity(self::LOG)->performedOn($subject)->event('merged')->withProperties($properties);

                if ($by !== null) {
                    $entry->causedBy($by);
                }

                $entry->log($description);
            }

            return FacilityMergeResult::merged($counts, $fields, $doctorIds);
        });

        // Departments were written to the pivot directly: the register
        // facility's search entry is refreshed after the commit (the draft
        // left the index when it was soft-deleted; doctors' entries do not
        // carry workplaces).
        if ($result->status === FacilityMergeResult::MERGED) {
            Facility::query()->find($intoId)?->searchable();
        }

        return $result;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<int> the doctors whose link moved
     */
    private function moveDoctors(int $fromId, int $intoId, array &$counts): array
    {
        $counts += ['doctors_moved' => 0, 'doctors_already_linked' => 0, 'doctors_locked' => 0];
        $moved = [];

        foreach (DB::table('doctor_facility')->where('facility_id', $fromId)->orderBy('doctor_id')->get() as $link) {
            $doctorId = (int) $link->doctor_id;
            $locked = (bool) FieldProvenance::query()
                ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)
                ->where('subject_id', $doctorId)
                ->where('field', 'facilities')
                ->value('locked');

            if ($locked) {
                $counts['doctors_locked']++;

                continue;
            }

            $existing = DB::table('doctor_facility')->where('doctor_id', $doctorId)->where('facility_id', $intoId)->first();

            if ($existing === null) {
                DB::table('doctor_facility')->where('doctor_id', $doctorId)->where('facility_id', $fromId)
                    ->update(['facility_id' => $intoId, 'updated_at' => now()]);
                $counts['doctors_moved']++;
            } else {
                $changes = array_filter([
                    'is_primary' => (bool) $link->is_primary && ! (bool) $existing->is_primary ? true : null,
                    'work_unit' => ($existing->work_unit === null || $existing->work_unit === '') && $link->work_unit !== null ? $link->work_unit : null,
                ], fn ($value): bool => $value !== null);

                if ($changes !== []) {
                    DB::table('doctor_facility')->where('doctor_id', $doctorId)->where('facility_id', $intoId)->update($changes + ['updated_at' => now()]);
                }

                DB::table('doctor_facility')->where('doctor_id', $doctorId)->where('facility_id', $fromId)->delete();
                $counts['doctors_already_linked']++;
            }

            $moved[] = $doctorId;
        }

        return $moved;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<string>
     */
    private function copyFields(Facility $from, Facility $into, array &$counts): array
    {
        $counts += ['fields_copied' => 0, 'fields_locked' => 0];
        $provenance = FieldProvenance::query()
            ->where('subject_type', FieldProvenance::SUBJECT_FACILITY)
            ->whereIn('subject_id', [$from->getKey(), $into->getKey()])
            ->get()
            ->groupBy(fn (FieldProvenance $row): string => $row->subject_id.':'.$row->field);
        $copied = [];

        foreach (self::FIELDS as $field) {
            $value = $from->getAttribute($field);
            $current = $into->getAttribute($field);

            if (self::blank($value) || ! self::blank($current)) {
                continue;
            }

            /** @var FieldProvenance|null $own */
            $own = $provenance->get($into->getKey().':'.$field)?->first();

            if ($own?->locked) {
                $counts['fields_locked']++;

                continue;
            }

            $into->setAttribute($field, $value);
            $copied[] = $field;

            /** @var FieldProvenance|null $theirs */
            $theirs = $provenance->get($from->getKey().':'.$field)?->first();

            if ($theirs !== null) {
                FieldProvenance::query()->updateOrCreate(
                    ['subject_type' => FieldProvenance::SUBJECT_FACILITY, 'subject_id' => $into->getKey(), 'field' => $field],
                    $theirs->only(['source', 'source_record_id', 'source_url', 'value', 'observed_at']),
                );
            }
        }

        if ($copied !== []) {
            // The previous value was empty: nothing for DeletesReplacedMedia to delete.
            $into->save();
            $counts['fields_copied'] = count($copied);
        }

        return $copied;
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function moveMedia(Facility $from, Facility $into, array &$counts): void
    {
        $counts += ['images_moved' => 0, 'images_already_there' => 0];
        $hashes = FacilityMedia::query()->where('facility_id', $into->getKey())->get(['kind', 'content_hash'])
            ->map(fn (FacilityMedia $media): string => $media->kind.':'.$media->content_hash)->all();

        foreach (FacilityMedia::query()->where('facility_id', $from->getKey())->orderBy('id')->get() as $media) {
            if (in_array($media->kind.':'.$media->content_hash, $hashes, true)) {
                $counts['images_already_there']++;

                continue;
            }

            DB::table('facility_media')->where('id', $media->getKey())->update(['facility_id' => $into->getKey(), 'updated_at' => now()]);
            $counts['images_moved']++;
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function moveDepartments(int $fromId, int $intoId, array &$counts): void
    {
        $counts += ['departments_moved' => 0];
        $existing = DB::table('department_facility')->where('facility_id', $intoId)->pluck('department_id')->map(fn ($id): int => (int) $id)->all();

        foreach (DB::table('department_facility')->where('facility_id', $fromId)->get() as $row) {
            if (! in_array((int) $row->department_id, $existing, true)) {
                DB::table('department_facility')->insert(['department_id' => $row->department_id, 'facility_id' => $intoId, 'created_at' => now(), 'updated_at' => now()]);
                $counts['departments_moved']++;
            }
        }

        DB::table('department_facility')->where('facility_id', $fromId)->delete();
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function moveSourceRecords(int $fromId, int $intoId, array &$counts): void
    {
        $counts['source_records_moved'] = SourceRecord::query()
            ->where('subject_type', FieldProvenance::SUBJECT_FACILITY)
            ->where('subject_id', $fromId)
            ->update(['subject_id' => $intoId, 'updated_at' => now()]);
        $counts += ['staff_entries_rekeyed' => 0, 'staff_entries_folded' => 0];
        $prefix = 'worker:'.$fromId.':';

        foreach (SourceRecord::query()->where('source', InstitutionsJsonImporter::SOURCE)->where('external_key', 'like', $prefix.'%')->orderBy('id')->get() as $record) {
            if (! str_starts_with((string) $record->external_key, $prefix)) {
                continue;
            }

            $key = mb_substr('worker:'.$intoId.':'.mb_substr((string) $record->external_key, mb_strlen($prefix)), 0, 191);
            $twin = SourceRecord::query()->where('source', InstitutionsJsonImporter::SOURCE)->where('external_key', $key)->first();

            if ($twin === null) {
                $record->forceFill(['external_key' => $key])->save();
                $counts['staff_entries_rekeyed']++;

                continue;
            }

            // The register facility's site lists the person too: one entry
            // stays (the newer sighting), the provenance points at it.
            if ($record->last_seen_at->gt($twin->last_seen_at)) {
                $twin->forceFill(['last_seen_at' => $record->last_seen_at, 'last_run_id' => $record->last_run_id])->save();
            }

            FieldProvenance::query()->where('source_record_id', $record->getKey())->update(['source_record_id' => $twin->getKey()]);
            $record->delete();
            $counts['staff_entries_folded']++;
        }
    }

    /**
     * „Trust this website“ (verification engine) was decided for the
     * website facility's id: it now speaks for the register facility.
     */
    private function moveSiteTrust(int $fromId, int $intoId): void
    {
        $from = EvidenceLoader::FLAGGED_SITE_KEY.$fromId;
        $into = EvidenceLoader::FLAGGED_SITE_KEY.$intoId;

        if (! ImportReviewItem::query()->where('item_key', $into)->exists()) {
            ImportReviewItem::query()->where('item_key', $from)->where('status', ImportReviewStatus::Resolved)
                ->where('resolution', EvidenceLoader::TRUSTED_RESOLUTION)
                ->update(['item_key' => $into, 'updated_at' => now()]);
        }
    }
}
