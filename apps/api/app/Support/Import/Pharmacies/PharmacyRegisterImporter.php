<?php

namespace App\Support\Import\Pharmacies;

use App\Enums\FacilityType;
use App\Enums\ImportReviewKind;
use App\Enums\ImportRunStatus;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\SourceRecord;
use App\Support\Import\DirectoryWriter;
use App\Support\Import\ImportContext;
use App\Support\Import\ProvenanceWriter;
use App\Support\Import\TextCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ФЗОМ's pharmacy contracts → directory pharmacies (import:pharmacies,
 * docs/data-import.md §4a).
 *
 * 1. FzomPharmacyReader folds the register's pharmacy rows into branches,
 *    one per ФЗО code (ShifraZU): name, legal entity, tax number, address,
 *    town. No person is read.
 * 2. Each branch is matched to a directory facility: by its source record,
 *    then by ФЗО code (facilities.fzo_code), then — pharmacies staff
 *    entered by hand, without a code — by name key and town; several such
 *    pharmacies are never guessed (review queue). A code already held by a
 *    facility that is not a pharmacy is queued, not taken over. A pharmacy
 *    staff deleted is not created again.
 * 3. Values go through ProvenanceWriter (locks, conflicts, changes on
 *    public profiles); a new pharmacy is a hidden draft with a „new“ review
 *    item. The verification engine verifies it from this register
 *    (rule fzom_register), so „Publish all verified“ publishes them in bulk.
 * 4. A complete snapshot counts a missed run for each branch it no longer
 *    lists ("missing" item after import.missing_after_runs); a snapshot
 *    that would miss more than import.max_missing_ratio stops the apply
 *    before anything is written.
 * 5. Last, the on-duty schedule held (every month) is linked again
 *    (OnDutyLinker), as the on-duty source: shifts get their pharmacy,
 *    pharmacies without a phone get the schedule's.
 */
final class PharmacyRegisterImporter
{
    public const SOURCE = 'fzom-pharmacies';

    public function __construct(private readonly FzomPharmacyReader $reader) {}

    /**
     * @param  array<string, string>  $files  label => path
     */
    public static function isComplete(array $files): bool
    {
        return array_diff(array_keys((array) config('import.pharmacies.files')), array_keys($files)) === [];
    }

    /**
     * @param  array<string, string>  $files  label => local path
     */
    public function import(ImportContext $context, array $files): void
    {
        $branches = [];

        foreach ($files as $label => $path) {
            $branches += $this->reader->branches($path, (string) $label);
            $context->increment('rows_skipped_invalid', $this->reader->skipped);
        }

        if ($branches === []) {
            throw new RuntimeException('The ФЗОМ register lists no pharmacy contract; refusing to import an empty list.');
        }

        $complete = self::isComplete($files);
        $context->increment('facilities_in_source', count($branches));

        if ($complete) {
            $this->guardAgainstMassMissing($context, array_map('strval', array_keys($branches)));
        } else {
            $context->increment('partial_snapshot');
        }

        $provenance = new ProvenanceWriter($context);
        $writer = new DirectoryWriter($context, $provenance);
        $seen = $this->importBranches($context, $writer, $branches);

        $now = now();

        foreach (array_chunk(array_map(fn ($code): string => 'pharmacy:'.$code, array_keys($branches)), 1000) as $chunk) {
            SourceRecord::query()->where('source', self::SOURCE)->whereIn('external_key', $chunk)->toBase()->update(['last_seen_at' => $now]);
        }

        if ($complete) {
            $this->markMissing($context, $seen);
        }

        $provenance->flushChanges();

        // The on-duty schedule, as its own source (provenance of the phone
        // numbers, its review items), inside this run.
        $duty = new ImportContext($context->run, OnDutyPharmacyImporter::SOURCE, $context->dryRun, $context->observedAt);
        (new OnDutyLinker)->relinkAll($duty);
        $context->absorb($duty, 'duty_');
    }

    /**
     * @param  array<string, array{code: string, tax_number: string|null, legal_name: string, branch: string, address: string|null, town: string|null, valid_to: string|null}>  $branches
     * @return list<int> facility ids seen in this run
     */
    private function importBranches(ImportContext $context, DirectoryWriter $writer, array $branches): array
    {
        $seen = [];

        foreach (array_chunk($branches, max(1, (int) config('import.batch_size')), true) as $batch) {
            DB::transaction(function () use ($context, $writer, $batch, &$seen): void {
                $codes = array_map('strval', array_keys($batch));
                $records = SourceRecord::query()->where('source', self::SOURCE)
                    ->whereIn('external_key', array_map(fn (string $code): string => 'pharmacy:'.$code, $codes))
                    ->get()->keyBy('external_key');
                $byCode = Facility::withTrashed()->whereIn('fzo_code', $codes)->get()->keyBy('fzo_code');
                $writer->provenance()->preload(FieldProvenance::SUBJECT_FACILITY, array_values(array_unique(array_merge(
                    $byCode->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                    $records->pluck('subject_id')->filter()->map(fn ($id): int => (int) $id)->all(),
                ))));

                foreach ($batch as $code => $branch) {
                    $code = (string) $code;
                    $town = TextCase::place($branch['town']);
                    $name = PharmacyName::clean($branch['branch'], $branch['legal_name'], $town)->value;
                    $address = TextCase::place($branch['address']);
                    $ownership = PharmacyName::ownership($branch['legal_name']);
                    $label = $name.($town !== null ? ', '.$town : '');
                    $payload = $branch;

                    $record = $records->get('pharmacy:'.$code);
                    $facility = $record?->subject_id !== null ? Facility::withTrashed()->find($record->subject_id) : null;
                    $facility ??= $byCode->get($code);

                    if ($facility === null) {
                        $byName = $this->handEnteredPharmacies($name, $town, $address);

                        if (count($byName) > 1) {
                            $context->increment('pharmacies_ambiguous');
                            $context->review(ImportReviewKind::Unmatched, 'fzom-pharmacy:'.$code, 'Several pharmacies match the ФЗОМ pharmacy '.$label, [
                                'reason' => 'ambiguous_pharmacy',
                                'fzo_code' => $code,
                                'name' => $name,
                                'town' => $town,
                                'address' => $address,
                                'candidate_facility_ids' => $byName,
                                'hint' => 'Give one of them the ФЗО code '.$code.' (or delete the duplicate); the next import updates it.',
                            ]);

                            continue;
                        }

                        $facility = $byName !== [] ? Facility::query()->find($byName[0]) : null;
                    }

                    if ($facility?->trashed()) {
                        // Deleted by staff: never brought back.
                        $context->increment('pharmacies_deleted_by_staff');

                        continue;
                    }

                    if ($facility !== null && $facility->type !== FacilityType::Pharmacy) {
                        $context->increment('pharmacies_code_on_other_facility');
                        $context->review(ImportReviewKind::Unmatched, 'fzom-pharmacy:'.$code, 'ФЗОМ pharmacy code is on a facility that is not a pharmacy: '.$label, [
                            'reason' => 'code_on_other_facility',
                            'fzo_code' => $code,
                            'name' => $name,
                            'town' => $town,
                            'address' => $address,
                            'candidate_facility_ids' => [(int) $facility->getKey()],
                            'hint' => 'The facility shown holds this pharmacy\'s ФЗО code. Remove the code from it (or make it a pharmacy) and the next import creates or updates the pharmacy.',
                        ], $facility);

                        continue;
                    }

                    $stored = $writer->sourceRecord('pharmacy:'.$code, FieldProvenance::SUBJECT_FACILITY, $payload, $record);

                    if ($facility !== null && $stored['unchanged'] && $record?->subject_id === $facility->getKey()) {
                        $seen[] = (int) $facility->getKey();
                        $context->increment('facilities_unchanged');

                        continue;
                    }

                    $isNew = $facility === null;
                    $facility ??= $writer->newFacility($name, $town, FacilityType::Pharmacy);
                    $recordId = (int) $stored['record']->getKey();
                    $p = $writer->provenance();

                    $p->scalar($facility, 'name', $name, $isNew, $recordId, $label);
                    $p->scalar($facility, 'city', $town, $isNew, $recordId, $label);
                    $p->scalar($facility, 'address', $address, $isNew, $recordId, $label);
                    $p->scalar($facility, 'ownership', $ownership, $isNew, $recordId, $label);

                    // Keys, not displayed values: set when empty, never fought over.
                    $facility->fzo_code ??= $code;
                    $facility->tax_number ??= $branch['tax_number'] !== null ? mb_substr($branch['tax_number'], 0, 16) : null;

                    $writer->save($facility);
                    $stored['record']->forceFill(['subject_id' => $facility->getKey()])->save();
                    $seen[] = (int) $facility->getKey();
                    ImportReviewItem::autoResolve(self::SOURCE, ImportReviewKind::Unmatched, 'fzom-pharmacy:'.$code, 'matched');

                    if ($isNew) {
                        $context->increment('facilities_created');
                        $context->record('pharmacy', 'create', $facility, $label, 'fzo_code', null, $code);
                        $context->review(ImportReviewKind::New, 'facility:'.$facility->getKey(), $label, ['type' => FacilityType::Pharmacy->value], $facility);
                    } else {
                        $context->increment('facilities_matched');
                    }
                }
            });
        }

        return $seen;
    }

    /**
     * Pharmacies staff entered (no ФЗО code, not from this import) with the
     * branch's name key in its town — and, when both have an address, a
     * compatible one (a chain's branches share their name; the hand-entered
     * profile of another street is not this branch).
     *
     * @return list<int>
     */
    private function handEnteredPharmacies(string $name, ?string $town, ?string $address = null): array
    {
        if ($town === null) {
            return [];
        }

        $townKey = OnDutyNames::townKey($town);
        $key = OnDutyNames::pharmacyKey($name, $town);
        $branchWords = array_filter(explode(' ', OnDutyLinker::addressKey($address)));

        return Facility::query()->pharmacy()
            ->whereNull('fzo_code')
            ->where(fn ($query) => $query->whereNull('import_source')->orWhere('import_source', '!=', self::SOURCE))
            ->whereNotNull('city')
            ->get(['id', 'name', 'city', 'address'])
            ->filter(fn (Facility $f): bool => OnDutyNames::townKey((string) $f->city) === $townKey && OnDutyNames::pharmacyKey((string) $f->name, (string) $f->city) === $key)
            ->filter(function (Facility $f) use ($branchWords): bool {
                $own = array_filter(explode(' ', OnDutyLinker::addressKey($f->address)));

                return $branchWords === [] || $own === [] || array_diff($branchWords, $own) === [] || array_diff($own, $branchWords) === [];
            })
            ->map(fn (Facility $f): int => (int) $f->getKey())
            ->values()->all();
    }

    /**
     * A truncated file looks like most pharmacies left: an apply stops
     * before writing anything; a dry run reports it.
     *
     * @param  list<string>  $codes
     */
    private function guardAgainstMassMissing(ImportContext $context, array $codes): void
    {
        $previous = ImportRun::query()
            ->where('source', self::SOURCE)
            ->where('dry_run', false)
            ->where('status', ImportRunStatus::Succeeded)
            ->whereKeyNot($context->run->getKey())
            ->latest('id')
            ->first();

        if ($previous === null) {
            return;
        }

        $baseline = fn () => SourceRecord::query()->where('source', self::SOURCE)->where('last_seen_at', '>=', $previous->started_at);
        $known = $baseline()->count();

        if ($known === 0) {
            return;
        }

        $present = 0;

        foreach (array_chunk($codes, 1000) as $chunk) {
            $present += $baseline()->whereIn('external_key', array_map(fn (string $code): string => 'pharmacy:'.$code, $chunk))->count();
        }

        $ratio = ($known - $present) / $known;
        $context->increment('pharmacies_absent_this_run', $known - $present);

        if ($ratio > (float) config('import.max_missing_ratio')) {
            $message = sprintf('Safety stop: %d of the %d pharmacies in the previous snapshot are absent from this one (%.0f%%).', $known - $present, $known, $ratio * 100);

            if (! $context->dryRun) {
                throw new RuntimeException($message.' Check the source file; nothing was written.');
            }

            $context->increment('safety_stop_would_trigger');
        }
    }

    /**
     * @param  list<int>  $seenIds
     */
    private function markMissing(ImportContext $context, array $seenIds): void
    {
        $threshold = max(1, (int) config('import.missing_after_runs'));
        $known = SourceRecord::query()->where('source', self::SOURCE)->whereNotNull('subject_id')
            ->pluck('subject_id')->map(fn ($id): int => (int) $id)->unique()->all();
        $seen = array_flip($seenIds);
        $now = now();

        foreach (array_chunk($seenIds, 1000) as $chunk) {
            Facility::withTrashed()->whereIn('id', $chunk)->toBase()->update(['import_missing_runs' => 0, 'import_last_seen_at' => $now]);
            ImportReviewItem::query()->open()->where('source', self::SOURCE)->where('kind', ImportReviewKind::Missing)
                ->where('subject_type', FieldProvenance::SUBJECT_FACILITY)->whereIn('subject_id', $chunk)
                ->update(['status' => 'resolved', 'resolution' => 'back_in_source', 'resolved_at' => $now, 'updated_at' => $now]);
        }

        $absent = array_values(array_filter($known, fn (int $id): bool => ! isset($seen[$id])));

        foreach (array_chunk($absent, 500) as $chunk) {
            Facility::withTrashed()->whereIn('id', $chunk)->toBase()->increment('import_missing_runs');

            Facility::query()->whereIn('id', $chunk)->where('import_missing_runs', $threshold)->get()
                ->each(function (Facility $pharmacy) use ($context): void {
                    $label = (string) $pharmacy->name;
                    $context->record('pharmacy', 'missing', $pharmacy, $label, note: 'Absent from '.$pharmacy->import_missing_runs.' consecutive runs.');
                    $context->review(ImportReviewKind::Missing, FieldProvenance::SUBJECT_FACILITY.':'.$pharmacy->getKey(), $label, [
                        'runs' => (int) $pharmacy->import_missing_runs,
                        'published' => (bool) $pharmacy->is_published,
                        'last_seen_at' => $pharmacy->import_last_seen_at?->toDateTimeString(),
                        'hint' => 'No longer under contract with ФЗОМ. It may still be open without a ФЗОМ contract: hide it only if it closed.',
                    ], $pharmacy);
                });
        }

        $context->increment('facilities_absent', count($absent));
    }
}
