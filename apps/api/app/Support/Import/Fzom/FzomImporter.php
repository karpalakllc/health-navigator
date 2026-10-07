<?php

namespace App\Support\Import\Fzom;

use App\Enums\FacilityType;
use App\Enums\ImportReviewKind;
use App\Enums\ImportRunStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\SourceRecord;
use App\Models\SpecialtyAlias;
use App\Support\Import\DirectoryWriter;
use App\Support\Import\ImportContext;
use App\Support\Import\ImportSuppressions;
use App\Support\Import\NameKey;
use App\Support\Import\Names\FacilityName;
use App\Support\Import\Names\PersonName;
use App\Support\Import\ProvenanceWriter;
use App\Support\Import\SpecialtyResolver;
use App\Support\Import\TextCase;
use App\Support\UrgentCare\UrgentCareDeriver;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports the ФЗОМ „Шифрарник на лекари“ (primary care + specialist files).
 *
 * 1. Stream both files and aggregate in memory: facilities by ФЗО code (or
 *    tax number), doctors by facsimile. Pharmacy contracts are dropped, as
 *    are people whose every specialty is a non-physician profession.
 * 2. Facilities first, then doctors, each in batches of one transaction.
 *    Matching: facility by ФЗО code, then by tax number; doctor by
 *    facsimile, then by normalised name + shared facility. Several name
 *    matches are never guessed, and a name + specialty + town match
 *    without a shared workplace is never merged: both go to the review
 *    queue.
 * 3. Values are written through ProvenanceWriter (locks, conflicts,
 *    changes on published profiles); new records are hidden drafts.
 * 4. Records a complete snapshot no longer lists count a missed run; after
 *    `missing_after_runs` consecutive misses they are queued as "missing".
 *    Nothing is deleted or unpublished. A partial snapshot (one of the two
 *    files) never counts a miss.
 */
final class FzomImporter
{
    public const SOURCE = 'fzom';

    public function __construct(private readonly FzomXmlReader $reader) {}

    /**
     * A complete snapshot has every file the source publishes (both ФЗОМ
     * lists). Only a complete one can tell that a record left the source.
     *
     * @param  array<string, string>  $files  label => local path
     */
    public static function isComplete(array $files): bool
    {
        return array_diff(array_keys((array) config('import.fzom.files')), array_keys($files)) === [];
    }

    /**
     * @param  array<string, string>  $files  label => local path; a partial set (one file) updates what it lists and never marks anything missing
     */
    public function import(ImportContext $context, array $files): void
    {
        [$facilities, $doctors] = $this->aggregate($context, $files);
        $complete = self::isComplete($files);

        // Before anything is written (aliases, specialties and review items
        // included): a truncated file must leave the database untouched.
        if ($complete) {
            $this->guardAgainstMassMissing($context, array_map('strval', array_keys($doctors)));
        } else {
            $context->increment('partial_snapshot');
        }

        $provenance = new ProvenanceWriter($context);
        $writer = new DirectoryWriter($context, $provenance);
        $specialties = new SpecialtyResolver($context, self::SOURCE);

        $doctors = $this->resolveDoctors($context, $doctors, $specialties);
        $facilities = array_intersect_key($facilities, $this->referencedFacilities($doctors));
        $context->increment('facilities_in_source', count($facilities));
        $context->increment('doctors_in_source', count($doctors));

        $facilityIds = $this->importFacilities($context, $writer, $facilities);
        $seenDoctorIds = $this->importDoctors($context, $writer, $doctors, $facilityIds, new ImportSuppressions);

        $this->touchSeen(array_merge(
            array_map(fn ($key): string => 'facility:'.$key, array_keys($facilities)),
            array_map(fn ($fax): string => 'doctor:'.$fax, array_keys($doctors)),
        ));

        if ($complete) {
            $this->markMissing($context, FieldProvenance::SUBJECT_FACILITY, array_values($facilityIds));
            $this->markMissing($context, FieldProvenance::SUBJECT_DOCTOR, $seenDoctorIds);
        }

        $provenance->flushChanges();

        // Work units such as „Ургентен центар“ or „Служба за итна медицинска
        // помош“ switch on the facility's urgent-care flags (docs/urgent-care.md).
        (new UrgentCareDeriver)->run($context);
    }

    /**
     * source_records.last_seen_at = the last run that listed the record,
     * changed or not (the safety stop's baseline).
     *
     * @param  list<string>  $externalKeys
     */
    private function touchSeen(array $externalKeys): void
    {
        $now = now();

        foreach (array_chunk($externalKeys, 1000) as $chunk) {
            SourceRecord::query()->where('source', self::SOURCE)->whereIn('external_key', $chunk)->toBase()->update(['last_seen_at' => $now]);
        }
    }

    /**
     * @param  array<string, string>  $files
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<string, mixed>>}
     */
    private function aggregate(ImportContext $context, array $files): array
    {
        $excludedTypes = array_map('intval', (array) config('import.fzom.excluded_contract_types'));
        $facilities = [];
        $doctors = [];

        $skippedBefore = $this->reader->skipped;

        foreach ($files as $label => $path) {
            foreach ($this->reader->rows($path, $label) as $row) {
                $context->increment('rows_read');

                if (in_array($row->contractTypeId, $excludedTypes, true)) {
                    $context->increment('rows_excluded_pharmacy');

                    continue;
                }

                $facilityKey = self::facilityKey($row);

                if ($facilityKey === null) {
                    $context->increment('rows_without_facility');

                    continue;
                }

                if ($row->facilityCode !== null) {
                    $facilities[$facilityKey]['codes'][$row->facilityCode] = true;
                }

                $facilities[$facilityKey]['codes'] ??= [];
                $facilities[$facilityKey]['tax_number'] ??= $row->taxNumber;
                $facilities[$facilityKey]['names'][$row->facilityName] = ($facilities[$facilityKey]['names'][$row->facilityName] ?? 0) + 1;
                $facilities[$facilityKey]['towns'][(string) $row->town] = ($facilities[$facilityKey]['towns'][(string) $row->town] ?? 0) + 1;
                $facilities[$facilityKey]['addresses'][(string) $row->address] = ($facilities[$facilityKey]['addresses'][(string) $row->address] ?? 0) + 1;
                $facilities[$facilityKey]['contract_types'][$row->contractTypeId] = $row->contractType;

                $doctors[$row->facsimile]['first'] ??= $row->firstName;
                $doctors[$row->facsimile]['last'] ??= $row->lastName;
                $doctors[$row->facsimile]['contracts'][] = [
                    'facility' => $facilityKey,
                    'work_unit' => $row->workUnit,
                    'contract_type_id' => $row->contractTypeId,
                    'contract_type' => $row->contractType,
                    'specialties' => $row->specialties,
                    'activity' => $row->activity,
                    'town' => $row->town,
                    'valid_from' => $row->validFrom,
                    'valid_to' => $row->validTo,
                ];
            }
        }

        $context->increment('rows_skipped_invalid', $this->reader->skipped - $skippedBefore);

        if ($doctors === []) {
            throw new RuntimeException('The ФЗОМ files contain no doctor rows; refusing to import an empty list.');
        }

        foreach ($facilities as $key => $facility) {
            $codes = array_map('strval', array_keys($facility['codes']));
            sort($codes, SORT_STRING);
            $facilities[$key]['codes'] = $codes;
            // The register code shown to staff and used for matching: the
            // lowest of the institution's contract-unit codes (stable).
            $facilities[$key]['code'] = $codes[0] ?? null;
        }

        return [$facilities, $doctors];
    }

    /**
     * Specialties per doctor; drops non-physicians.
     *
     * @param  array<string, array<string, mixed>>  $doctors
     * @return array<string, array<string, mixed>>
     */
    private function resolveDoctors(ImportContext $context, array $doctors, SpecialtyResolver $resolver): array
    {
        foreach ($doctors as $facsimile => $doctor) {
            $ids = [];
            $excluded = [];
            $unmapped = [];

            foreach ($doctor['contracts'] as $contract) {
                $result = $resolver->resolve($contract['specialties'], $contract['contract_type_id']);
                $ids = array_merge($ids, $result['ids']);
                $excluded = array_merge($excluded, $result['excluded']);
                $unmapped = array_merge($unmapped, $result['unmapped']);
            }

            // No specialty on any contract: the activity (`Dejnost`) of the
            // work unit, when it names a specialty unambiguously.
            if ($ids === [] && $excluded === [] && $unmapped === []) {
                foreach ($doctor['contracts'] as $contract) {
                    $slug = FzomSpecialtyCatalog::activityDefaultFor(SpecialtyAlias::keyFor((string) $contract['activity']));

                    if ($slug !== null) {
                        $ids[] = $resolver->specialtyId($slug);
                    }
                }

                $context->increment($ids === [] ? 'doctors_without_specialty' : 'doctors_specialty_from_activity');
            }

            if ($ids === [] && $excluded !== [] && $unmapped === []) {
                $context->increment('people_excluded_non_physician');
                unset($doctors[$facsimile]);

                continue;
            }

            $doctors[$facsimile]['specialty_ids'] = array_values(array_unique($ids));
            $doctors[$facsimile]['unmapped'] = array_values(array_unique($unmapped));
        }

        return $doctors;
    }

    /**
     * @param  array<string, array<string, mixed>>  $doctors
     * @return array<string, true>
     */
    private function referencedFacilities(array $doctors): array
    {
        $keys = [];

        foreach ($doctors as $doctor) {
            foreach ($doctor['contracts'] as $contract) {
                $keys[$contract['facility']] = true;
            }
        }

        return $keys;
    }

    /**
     * A truncated or half-generated file looks like most doctors left.
     * An apply stops before writing; a dry run reports it.
     *
     * The baseline is the previous complete snapshot: the doctors listed by
     * the last successful complete apply (and any partial run since), not
     * every doctor ever seen, so ordinary turnover never adds up to a stop.
     *
     * @param  list<string>  $facsimiles  every facsimile in this snapshot
     */
    private function guardAgainstMassMissing(ImportContext $context, array $facsimiles): void
    {
        $previous = ImportRun::query()
            ->where('source', self::SOURCE)
            ->where('dry_run', false)
            ->where('status', ImportRunStatus::Succeeded)
            ->whereKeyNot($context->run->getKey())
            ->latest('id')
            ->limit(20)
            ->get()
            ->first(fn (ImportRun $run): bool => (bool) ($run->source_meta['complete'] ?? true));

        if ($previous === null) {
            return;
        }

        $baseline = fn () => SourceRecord::query()->where('source', self::SOURCE)
            ->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)
            ->where('last_seen_at', '>=', $previous->started_at);
        $known = $baseline()->count();

        if ($known === 0) {
            return;
        }

        $present = 0;

        foreach (array_chunk($facsimiles, 1000) as $chunk) {
            $present += $baseline()->whereIn('external_key', array_map(fn ($fax): string => 'doctor:'.$fax, $chunk))->count();
        }

        $ratio = ($known - $present) / $known;
        $context->increment('doctors_absent_this_run', $known - $present);

        if ($ratio > (float) config('import.max_missing_ratio')) {
            $message = sprintf('Safety stop: %d of the %d doctors in the previous complete snapshot are absent from this one (%.0f%%).', $known - $present, $known, $ratio * 100);

            if (! $context->dryRun) {
                throw new RuntimeException($message.' Check the source files; nothing was written.');
            }

            $context->increment('safety_stop_would_trigger');
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $facilities
     * @return array<string, int> facility key => our facility id
     */
    private function importFacilities(ImportContext $context, DirectoryWriter $writer, array $facilities): array
    {
        $ids = [];
        $hospitalTypes = array_map('intval', (array) config('import.fzom.hospital_contract_types'));
        $labTypes = array_map('intval', (array) config('import.fzom.laboratory_contract_types'));

        foreach (array_chunk($facilities, (int) config('import.batch_size'), true) as $batch) {
            DB::transaction(function () use ($context, $writer, $batch, $hospitalTypes, $labTypes, &$ids): void {
                $records = SourceRecord::query()->where('source', self::SOURCE)
                    ->whereIn('external_key', array_map(fn ($key): string => 'facility:'.$key, array_keys($batch)))
                    ->get()->keyBy('external_key');
                $allCodes = array_merge([], ...array_column($batch, 'codes'));
                $byCode = Facility::withTrashed()->whereIn('fzo_code', $allCodes)->get()->keyBy('fzo_code');
                $writer->provenance()->preload(FieldProvenance::SUBJECT_FACILITY, $byCode->pluck('id')->map(fn ($id): int => (int) $id)->all());

                foreach ($batch as $key => $data) {
                    $types = array_keys($data['contract_types']);
                    $type = match (true) {
                        array_intersect($types, $hospitalTypes) !== [] => FacilityType::Hospital,
                        array_diff($types, $labTypes) === [] => FacilityType::Laboratory,
                        default => FacilityType::Clinic,
                    };
                    $rawName = self::mostCommon($data['names']);
                    $town = TextCase::place(self::mostCommon($data['towns']));
                    $name = FacilityName::clean($rawName, $town)->value;
                    $address = TextCase::place(self::mostCommon($data['addresses']));
                    $ownership = self::ownership($rawName, array_values($data['contract_types']));

                    $payload = [
                        'name' => $rawName,
                        'town' => $town,
                        'address' => $address,
                        'type' => $type->value,
                        'ownership' => $ownership,
                        'contract_types' => $types,
                        'codes' => $data['codes'],
                    ];
                    $sourceRecord = $records->get('facility:'.$key);
                    $facility = $sourceRecord?->subject_id !== null ? Facility::withTrashed()->find($sourceRecord->subject_id) : null;
                    $facility ??= collect($data['codes'])->map(fn (string $code) => $byCode->get($code))->filter()->first();
                    $facility ??= $this->facilityByTaxNumber($data['tax_number']);

                    $stored = $writer->sourceRecord('facility:'.$key, FieldProvenance::SUBJECT_FACILITY, $payload, $sourceRecord);

                    if ($facility !== null && $stored['unchanged'] && $sourceRecord?->subject_id === $facility->getKey()) {
                        $ids[$key] = (int) $facility->getKey();
                        $context->increment('facilities_unchanged');

                        continue;
                    }

                    $isNew = $facility === null;
                    $facility ??= $writer->newFacility($name, $town, $type);
                    $label = $name.($town !== null ? ', '.$town : '');
                    $recordId = (int) $stored['record']->getKey();
                    $p = $writer->provenance();

                    $p->scalar($facility, 'name', $name, $isNew, $recordId, $label);
                    $p->scalar($facility, 'city', $town, $isNew, $recordId, $label);
                    $p->scalar($facility, 'address', $address, $isNew, $recordId, $label);
                    $p->scalar($facility, 'type', $type->value, $isNew, $recordId, $label);
                    $p->scalar($facility, 'ownership', $ownership, $isNew, $recordId, $label);

                    // Keys, not displayed values: set when empty, never fought over.
                    $facility->fzo_code ??= $data['code'];
                    $facility->tax_number ??= $data['tax_number'];

                    $writer->save($facility);
                    $stored['record']->forceFill(['subject_id' => $facility->getKey()])->save();
                    $ids[$key] = (int) $facility->getKey();

                    if ($isNew) {
                        $context->increment('facilities_created');
                        $context->record('facility', 'create', $facility, $label, 'type', null, $type->value);
                        $context->review(ImportReviewKind::New, 'facility:'.$facility->getKey(), $label, ['type' => $type->value], $facility);
                    } else {
                        $context->increment('facilities_matched');
                    }
                }
            });
        }

        return $ids;
    }

    private function facilityByTaxNumber(?string $taxNumber): ?Facility
    {
        if ($taxNumber === null) {
            return null;
        }

        // Only facilities staff entered (with a tax number and no ФЗО code):
        // the import's own uncoded facilities are found by source record.
        $candidates = Facility::withTrashed()
            ->where('tax_number', $taxNumber)
            ->whereNull('fzo_code')
            ->where(fn ($query) => $query->whereNull('import_source')->orWhere('import_source', '!=', self::SOURCE))
            ->limit(2)
            ->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $doctors
     * @param  array<string, int>  $facilityIds
     * @return list<int> doctor ids seen in this run
     */
    private function importDoctors(ImportContext $context, DirectoryWriter $writer, array $doctors, array $facilityIds, ImportSuppressions $suppressions): array
    {
        $seen = [];
        $primaryTypes = array_map('intval', (array) config('import.fzom.primary_care_contract_types'));

        foreach (array_chunk($doctors, (int) config('import.batch_size'), true) as $batch) {
            DB::transaction(function () use ($context, $writer, $batch, $facilityIds, $primaryTypes, $suppressions, &$seen): void {
                $records = SourceRecord::query()->where('source', self::SOURCE)
                    ->whereIn('external_key', array_map(fn ($fax): string => 'doctor:'.$fax, array_keys($batch)))
                    ->get()->keyBy('external_key');
                $byFacsimile = Doctor::withTrashed()->whereIn('fzo_facsimile', array_map('strval', array_keys($batch)))->get()->keyBy('fzo_facsimile');
                $writer->provenance()->preload(FieldProvenance::SUBJECT_DOCTOR, $byFacsimile->pluck('id')->map(fn ($id): int => (int) $id)->all());

                foreach ($batch as $facsimile => $data) {
                    $facsimile = (string) $facsimile;
                    $fullName = PersonName::clean(trim($data['first'].' '.$data['last']))->value;
                    $contracts = $data['contracts'];
                    usort($contracts, fn (array $a, array $b): int => [
                        in_array($b['contract_type_id'], $primaryTypes, true), $b['valid_from'] ?? '',
                    ] <=> [
                        in_array($a['contract_type_id'], $primaryTypes, true), $a['valid_from'] ?? '',
                    ]);

                    $links = [];

                    foreach ($contracts as $contract) {
                        $facilityId = $facilityIds[$contract['facility']] ?? null;

                        if ($facilityId !== null && ! isset($links[$facilityId])) {
                            $links[$facilityId] = [
                                'work_unit' => $contract['work_unit'] !== null ? mb_substr(TextCase::institution($contract['work_unit']), 0, 255) : null,
                                'contract_type' => mb_substr((string) $contract['contract_type'], 0, 255) ?: null,
                            ];
                        }
                    }

                    $primaryFacilityId = $facilityIds[$contracts[0]['facility']] ?? null;
                    $city = TextCase::place($contracts[0]['town']);
                    $specialtyIds = $data['specialty_ids'];
                    $payload = [
                        'name' => trim($data['first'].' '.$data['last']),
                        'specialty_ids' => $specialtyIds,
                        'unmapped' => $data['unmapped'],
                        'contracts' => array_map(fn (array $c): array => [
                            'facility' => $c['facility'], 'work_unit' => $c['work_unit'], 'type' => $c['contract_type_id'],
                            'specialties' => $c['specialties'], 'valid_from' => $c['valid_from'], 'valid_to' => $c['valid_to'],
                        ], $contracts),
                        'facility_ids' => array_keys($links),
                    ];

                    $sourceRecord = $records->get('doctor:'.$facsimile);
                    $doctor = $byFacsimile->get($facsimile);

                    // Removed on objection or deleted by staff: not created,
                    // updated or stored again (and never "missing").
                    if ($suppressions->facsimile($facsimile) || $suppressions->doctorId($doctor?->getKey())) {
                        $context->increment('doctors_suppressed');

                        if ($doctor !== null) {
                            $seen[] = (int) $doctor->getKey();
                        }

                        continue;
                    }

                    $stored = $writer->sourceRecord('doctor:'.$facsimile, FieldProvenance::SUBJECT_DOCTOR, $payload, $sourceRecord);

                    if ($doctor !== null && $stored['unchanged'] && $sourceRecord?->subject_id === $doctor->getKey()) {
                        $seen[] = (int) $doctor->getKey();
                        $context->increment('doctors_unchanged');

                        continue;
                    }

                    $possibleDuplicates = [];

                    if ($doctor === null) {
                        $byName = $this->nameCandidates($fullName, array_keys($links), $specialtyIds, $city);
                        $candidates = $byName['workplace'];

                        if (array_filter([...$candidates, ...$byName['town']], fn (int $id): bool => $suppressions->doctorId($id)) !== []
                            || ($candidates === [] && $byName['town'] === [] && $suppressions->name($fullName, $city, onlyWithoutFacsimile: true))) {
                            $context->increment('doctors_suppressed');

                            if ($sourceRecord === null) {
                                $stored['record']->delete();
                            }

                            continue;
                        }

                        if (count($candidates) > 1) {
                            $context->increment('doctors_ambiguous');
                            $context->review(
                                ImportReviewKind::Unmatched,
                                'fzom-doctor:'.$stored['record']->getKey(),
                                'Several existing profiles match '.$fullName,
                                ['reason' => 'ambiguous_name', 'candidate_doctor_ids' => $candidates, 'city' => $city],
                            );

                            continue;
                        }

                        $doctor = $candidates !== [] ? Doctor::query()->find($candidates[0]) : null;
                        $possibleDuplicates = $doctor === null ? $byName['town'] : [];
                    }

                    $isNew = $doctor === null;
                    $doctor ??= $writer->newDoctor($fullName, $city);
                    $label = $fullName.($city !== null ? ', '.$city : '');
                    $recordId = (int) $stored['record']->getKey();

                    $writer->provenance()->scalar($doctor, 'full_name', $fullName, $isNew, $recordId, $label);
                    $writer->provenance()->scalar($doctor, 'city', $city, $isNew, $recordId, $label);
                    $doctor->fzo_facsimile ??= $facsimile;
                    $doctor->import_source ??= self::SOURCE;
                    $writer->save($doctor);
                    $stored['record']->forceFill(['subject_id' => $doctor->getKey()])->save();

                    $writer->syncSpecialties($doctor, $specialtyIds, $label, $isNew);
                    $writer->syncFacilities($doctor, $links, $primaryFacilityId, $label, $isNew);
                    $seen[] = (int) $doctor->getKey();

                    if ($data['unmapped'] !== []) {
                        $context->increment('doctors_with_unmapped_specialty');
                    }

                    if ($possibleDuplicates !== []) {
                        // Same name, specialty and town as a profile staff made,
                        // but no shared workplace: kept apart, staff compare.
                        $context->increment('doctors_possible_duplicate');
                        $context->review(
                            ImportReviewKind::Unmatched,
                            'fzom-duplicate:'.$doctor->getKey(),
                            'Possibly the same person as an existing profile: '.$fullName,
                            ['reason' => 'possible_duplicate', 'candidate_doctor_ids' => $possibleDuplicates, 'city' => $city],
                            $doctor,
                        );
                    }

                    if ($isNew) {
                        $dental = $specialtyIds !== [] && collect($specialtyIds)->every(fn (int $id): bool => in_array($id, $this->dentalSpecialtyIds(), true));
                        $context->increment($dental ? 'dentists_created' : 'doctors_created');
                        $context->record('doctor', 'create', $doctor, $label);
                        $context->review(ImportReviewKind::New, 'doctor:'.$doctor->getKey(), $label, [
                            'dentist' => $dental,
                            // Mostly laboratory staff: check they are doctors before publishing.
                            'no_specialty' => $specialtyIds === [],
                        ], $doctor);
                    } else {
                        $context->increment('doctors_matched');
                    }
                }
            });
        }

        return $seen;
    }

    /**
     * Existing profiles without a facsimile that may be this person, by
     * normalised name (either word order):
     * - `workplace`: they share a facility — strong enough to merge (one) or
     *   to call ambiguous (several);
     * - `town`: same specialty in the same town but no shared workplace — a
     *   common name is not proof, so never merged: staff check it.
     *
     * @param  list<int>  $facilityIds
     * @param  list<int>  $specialtyIds
     * @return array{workplace: list<int>, town: list<int>}
     */
    private function nameCandidates(string $fullName, array $facilityIds, array $specialtyIds, ?string $city): array
    {
        $byName = Doctor::query()
            ->whereNull('fzo_facsimile')
            ->where(fn ($query) => $query->where('name_key', NameKey::for($fullName))->orWhere('name_key_sorted', NameKey::sorted($fullName)))
            ->get(['id', 'city']);

        if ($byName->isEmpty()) {
            return ['workplace' => [], 'town' => []];
        }

        $ids = $byName->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $atFacility = DB::table('doctor_facility')->whereIn('doctor_id', $ids)->whereIn('facility_id', $facilityIds ?: [0])->pluck('doctor_id')->map(fn ($id): int => (int) $id);
        $sameSpecialty = DB::table('doctor_specialty')->whereIn('doctor_id', $ids)->whereIn('specialty_id', $specialtyIds ?: [0])->pluck('doctor_id')->map(fn ($id): int => (int) $id);
        $cityKey = $city !== null ? NameKey::for($city) : null;

        return [
            'workplace' => $byName->filter(fn (Doctor $doctor): bool => $atFacility->contains((int) $doctor->id))
                ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'town' => $byName->filter(fn (Doctor $doctor): bool => ! $atFacility->contains((int) $doctor->id)
                && $sameSpecialty->contains((int) $doctor->id) && $cityKey !== null && NameKey::for((string) $doctor->city) === $cityKey)
                ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
        ];
    }

    /** @var list<int>|null */
    private ?array $dentalIds = null;

    /**
     * @return list<int>
     */
    private function dentalSpecialtyIds(): array
    {
        return $this->dentalIds ??= DB::table('specialties')
            ->where(fn ($query) => $query->where('slug', FzomSpecialtyCatalog::DENTAL_SLUG_PREFIX)->orWhere('slug', 'like', FzomSpecialtyCatalog::DENTAL_SLUG_PREFIX.'-%'))
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Counts a missed run for every record this source knew that was not in
     * this snapshot, resets those that are back, and queues the ones missed
     * `missing_after_runs` times in a row.
     *
     * @param  list<int>  $seenIds
     */
    private function markMissing(ImportContext $context, string $subjectType, array $seenIds): void
    {
        $model = $subjectType === FieldProvenance::SUBJECT_DOCTOR ? Doctor::class : Facility::class;
        $threshold = max(1, (int) config('import.missing_after_runs'));
        $known = SourceRecord::query()->where('source', self::SOURCE)->where('subject_type', $subjectType)
            ->whereNotNull('subject_id')->pluck('subject_id')->map(fn ($id): int => (int) $id)->unique()->all();
        $seen = array_flip($seenIds);
        $now = now();

        foreach (array_chunk($seenIds, 1000) as $chunk) {
            $model::withTrashed()->whereIn('id', $chunk)->toBase()->update(['import_missing_runs' => 0, 'import_last_seen_at' => $now]);
        }

        foreach (array_chunk($seenIds, 1000) as $chunk) {
            ImportReviewItem::query()->open()->where('source', self::SOURCE)->where('kind', ImportReviewKind::Missing)
                ->where('subject_type', $subjectType)->whereIn('subject_id', $chunk)
                ->update(['status' => 'resolved', 'resolution' => 'back_in_source', 'resolved_at' => $now, 'updated_at' => $now]);
        }

        $absent = array_values(array_filter($known, fn (int $id): bool => ! isset($seen[$id])));

        foreach (array_chunk($absent, 500) as $chunk) {
            $model::withTrashed()->whereIn('id', $chunk)->toBase()->increment('import_missing_runs');

            // Queued once, on the run that crosses the threshold: a dismissed
            // item stays dismissed while the record stays away.
            $model::query()->whereIn('id', $chunk)->where('import_missing_runs', $threshold)->get()
                ->each(function (Doctor|Facility $subject) use ($context, $subjectType): void {
                    $label = (string) ($subject->getAttribute('full_name') ?? $subject->getAttribute('name'));
                    $context->record($subjectType, 'missing', $subject, $label, note: 'Absent from '.$subject->import_missing_runs.' consecutive runs.');
                    $context->review(ImportReviewKind::Missing, $subjectType.':'.$subject->getKey(), $label, [
                        'runs' => (int) $subject->import_missing_runs,
                        'published' => (bool) $subject->is_published,
                        // A new absence (back in between) is a new item.
                        'last_seen_at' => $subject->import_last_seen_at?->toDateTimeString(),
                    ], $subject);
                });
        }

        $context->increment($subjectType === FieldProvenance::SUBJECT_DOCTOR ? 'doctors_absent' : 'facilities_absent', count($absent));
    }

    /**
     * One facility per institution: the legal entity (tax number, ЕДБ) in
     * one town. ФЗОМ lists an institution once per contract unit (a
     * hospital can have twenty ФЗО codes, all with the same name), so the
     * ФЗО code alone would split one hospital into many profiles. The tax
     * number plus town always carries a single institution name in the
     * register. Rows without a tax number fall back to the ФЗО code.
     */
    private static function facilityKey(FzomRow $row): ?string
    {
        if ($row->taxNumber !== null) {
            return 'edb:'.$row->taxNumber.':'.NameKey::for((string) $row->town);
        }

        return $row->facilityCode !== null ? 'zu:'.$row->facilityCode : null;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private static function mostCommon(array $counts): string
    {
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @param  list<string>  $contractTypes
     */
    private static function ownership(string $name, array $contractTypes): ?string
    {
        $haystack = mb_strtoupper($name.' '.implode(' ', $contractTypes), 'UTF-8');

        return match (true) {
            str_contains($haystack, 'ЈЗУ') => 'public',
            str_contains($haystack, 'ПЗУ') => 'private',
            default => null,
        };
    }
}
