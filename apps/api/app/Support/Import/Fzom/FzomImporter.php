<?php

namespace App\Support\Import\Fzom;

use App\Enums\FacilityType;
use App\Enums\ImportReviewKind;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\SourceRecord;
use App\Support\Import\DirectoryWriter;
use App\Support\Import\ImportContext;
use App\Support\Import\NameKey;
use App\Support\Import\ProvenanceWriter;
use App\Support\Import\SpecialtyResolver;
use App\Support\Import\TextCase;
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
 *    facsimile, then by normalised name + shared facility or same
 *    specialty and town. Several name matches are never guessed: they go to
 *    the review queue.
 * 3. Values are written through ProvenanceWriter (locks, conflicts,
 *    changes on published profiles); new records are hidden drafts.
 * 4. Records the source no longer lists count a missed run; after
 *    `missing_after_runs` consecutive misses they are queued as "missing".
 *    Nothing is deleted or unpublished.
 */
final class FzomImporter
{
    public const SOURCE = 'fzom';

    public function __construct(private readonly FzomXmlReader $reader) {}

    /**
     * @param  array<string, string>  $files  label => local path, all files of one complete snapshot
     */
    public function import(ImportContext $context, array $files): void
    {
        [$facilities, $doctors] = $this->aggregate($context, $files);

        $provenance = new ProvenanceWriter($context);
        $writer = new DirectoryWriter($context, $provenance);
        $specialties = new SpecialtyResolver($context, self::SOURCE);

        $doctors = $this->resolveDoctors($context, $doctors, $specialties);
        $facilities = array_intersect_key($facilities, $this->referencedFacilities($doctors));
        $context->increment('facilities_in_source', count($facilities));
        $context->increment('doctors_in_source', count($doctors));

        $this->guardAgainstMassMissing($context, array_keys($facilities), array_keys($doctors));

        $facilityIds = $this->importFacilities($context, $writer, $facilities);
        $seenDoctorIds = $this->importDoctors($context, $writer, $doctors, $facilityIds);

        $this->markMissing($context, FieldProvenance::SUBJECT_FACILITY, array_values($facilityIds));
        $this->markMissing($context, FieldProvenance::SUBJECT_DOCTOR, $seenDoctorIds);
        $provenance->flushChanges();
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

        foreach ($files as $label => $path) {
            foreach ($this->reader->rows($path, $label) as $row) {
                $context->increment('rows_read');

                if (in_array($row->contractTypeId, $excludedTypes, true)) {
                    $context->increment('rows_excluded_pharmacy');

                    continue;
                }

                $facilityKey = $row->facilityKey();

                if ($facilityKey === null) {
                    $context->increment('rows_without_facility');

                    continue;
                }

                $facilities[$facilityKey]['code'] ??= $row->facilityCode;
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

        if ($doctors === []) {
            throw new RuntimeException('The ФЗОМ files contain no doctor rows; refusing to import an empty list.');
        }

        return $this->foldUncodedFacilities($context, $facilities, $doctors);
    }

    /**
     * A few rows carry a tax number but no ФЗО code. When exactly one coded
     * facility in the snapshot has that tax number, they are that facility;
     * otherwise (no code, or several units under one tax number) they stay
     * a facility of their own rather than being guessed into one.
     *
     * @param  array<string, array<string, mixed>>  $facilities
     * @param  array<string, array<string, mixed>>  $doctors
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<string, mixed>>}
     */
    private function foldUncodedFacilities(ImportContext $context, array $facilities, array $doctors): array
    {
        $codesByTax = [];

        foreach ($facilities as $key => $facility) {
            if ($facility['code'] !== null && $facility['tax_number'] !== null) {
                $codesByTax[$facility['tax_number']][] = $key;
            }
        }

        $remap = [];

        foreach ($facilities as $key => $facility) {
            if ($facility['code'] === null && count($codesByTax[$facility['tax_number']] ?? []) === 1) {
                $remap[$key] = $codesByTax[$facility['tax_number']][0];
                unset($facilities[$key]);
            }
        }

        if ($remap === []) {
            return [$facilities, $doctors];
        }

        foreach ($doctors as $facsimile => $doctor) {
            foreach ($doctor['contracts'] as $index => $contract) {
                if (isset($remap[$contract['facility']])) {
                    $doctors[$facsimile]['contracts'][$index]['facility'] = $remap[$contract['facility']];
                }
            }
        }

        $context->increment('facilities_folded_by_tax_number', count($remap));

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
     * @param  list<string>  $facilityKeys
     * @param  list<string>  $facsimiles
     */
    private function guardAgainstMassMissing(ImportContext $context, array $facilityKeys, array $facsimiles): void
    {
        $known = SourceRecord::query()->where('source', self::SOURCE)->where('subject_type', FieldProvenance::SUBJECT_DOCTOR)->count();

        if ($known === 0) {
            return;
        }

        $present = 0;

        foreach (array_chunk($facsimiles, 1000) as $chunk) {
            $present += SourceRecord::query()->where('source', self::SOURCE)
                ->whereIn('external_key', array_map(fn ($fax): string => 'doctor:'.$fax, $chunk))
                ->count();
        }

        $ratio = ($known - $present) / $known;
        $context->increment('doctors_absent_this_run', $known - $present);

        if ($ratio > (float) config('import.max_missing_ratio')) {
            $message = sprintf('Safety stop: %d of %d known doctors are absent from this snapshot (%.0f%%).', $known - $present, $known, $ratio * 100);

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
                $byCode = Facility::withTrashed()->whereIn('fzo_code', array_filter(array_column($batch, 'code')))->get()->keyBy('fzo_code');
                $writer->provenance()->preload(FieldProvenance::SUBJECT_FACILITY, $byCode->pluck('id')->map(fn ($id): int => (int) $id)->all());

                foreach ($batch as $key => $data) {
                    $types = array_keys($data['contract_types']);
                    $type = match (true) {
                        array_intersect($types, $hospitalTypes) !== [] => FacilityType::Hospital,
                        array_diff($types, $labTypes) === [] => FacilityType::Laboratory,
                        default => FacilityType::Clinic,
                    };
                    $rawName = self::mostCommon($data['names']);
                    $name = TextCase::institution($rawName);
                    $town = TextCase::place(self::mostCommon($data['towns']));
                    $address = TextCase::place(self::mostCommon($data['addresses']));
                    $ownership = self::ownership($rawName, array_values($data['contract_types']));

                    $payload = [
                        'name' => $rawName,
                        'town' => $town,
                        'address' => $address,
                        'type' => $type->value,
                        'ownership' => $ownership,
                        'contract_types' => $types,
                    ];
                    $sourceRecord = $records->get('facility:'.$key);
                    $facility = $sourceRecord?->subject_id !== null ? Facility::withTrashed()->find($sourceRecord->subject_id) : null;
                    $facility ??= $data['code'] !== null ? $byCode->get($data['code']) : null;
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
    private function importDoctors(ImportContext $context, DirectoryWriter $writer, array $doctors, array $facilityIds): array
    {
        $seen = [];
        $primaryTypes = array_map('intval', (array) config('import.fzom.primary_care_contract_types'));

        foreach (array_chunk($doctors, (int) config('import.batch_size'), true) as $batch) {
            DB::transaction(function () use ($context, $writer, $batch, $facilityIds, $primaryTypes, &$seen): void {
                $records = SourceRecord::query()->where('source', self::SOURCE)
                    ->whereIn('external_key', array_map(fn ($fax): string => 'doctor:'.$fax, array_keys($batch)))
                    ->get()->keyBy('external_key');
                $byFacsimile = Doctor::withTrashed()->whereIn('fzo_facsimile', array_map('strval', array_keys($batch)))->get()->keyBy('fzo_facsimile');
                $writer->provenance()->preload(FieldProvenance::SUBJECT_DOCTOR, $byFacsimile->pluck('id')->map(fn ($id): int => (int) $id)->all());

                foreach ($batch as $facsimile => $data) {
                    $facsimile = (string) $facsimile;
                    $fullName = TextCase::person(trim($data['first'].' '.$data['last']));
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
                    $stored = $writer->sourceRecord('doctor:'.$facsimile, FieldProvenance::SUBJECT_DOCTOR, $payload, $sourceRecord);

                    if ($doctor !== null && $stored['unchanged'] && $sourceRecord?->subject_id === $doctor->getKey()) {
                        $seen[] = (int) $doctor->getKey();
                        $context->increment('doctors_unchanged');

                        continue;
                    }

                    if ($doctor === null) {
                        $candidates = $this->nameCandidates($fullName, array_keys($links), $specialtyIds, $city);

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

                    if ($isNew) {
                        $dental = $specialtyIds !== [] && collect($specialtyIds)->every(fn (int $id): bool => in_array($id, $this->dentalSpecialtyIds(), true));
                        $context->increment($dental ? 'dentists_created' : 'doctors_created');
                        $context->record('doctor', 'create', $doctor, $label);
                        $context->review(ImportReviewKind::New, 'doctor:'.$doctor->getKey(), $label, ['dentist' => $dental], $doctor);
                    } else {
                        $context->increment('doctors_matched');
                    }
                }
            });
        }

        return $seen;
    }

    /**
     * Existing profiles without a facsimile that are this person: same
     * normalised name (either word order) and a shared facility, or a shared
     * specialty in the same town.
     *
     * @param  list<int>  $facilityIds
     * @param  list<int>  $specialtyIds
     * @return list<int>
     */
    private function nameCandidates(string $fullName, array $facilityIds, array $specialtyIds, ?string $city): array
    {
        $byName = Doctor::query()
            ->whereNull('fzo_facsimile')
            ->where(fn ($query) => $query->where('name_key', NameKey::for($fullName))->orWhere('name_key_sorted', NameKey::sorted($fullName)))
            ->get(['id', 'city']);

        if ($byName->isEmpty()) {
            return [];
        }

        $ids = $byName->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $atFacility = DB::table('doctor_facility')->whereIn('doctor_id', $ids)->whereIn('facility_id', $facilityIds ?: [0])->pluck('doctor_id');
        $sameSpecialty = DB::table('doctor_specialty')->whereIn('doctor_id', $ids)->whereIn('specialty_id', $specialtyIds ?: [0])->pluck('doctor_id');
        $cityKey = $city !== null ? NameKey::for($city) : null;

        return $byName
            ->filter(fn (Doctor $doctor): bool => $atFacility->contains($doctor->id)
                || ($sameSpecialty->contains($doctor->id) && $cityKey !== null && NameKey::for((string) $doctor->city) === $cityKey))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
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

            $model::query()->whereIn('id', $chunk)->where('import_missing_runs', '>=', $threshold)->get()
                ->each(function (Doctor|Facility $subject) use ($context, $subjectType): void {
                    $label = (string) ($subject->getAttribute('full_name') ?? $subject->getAttribute('name'));
                    $context->record($subjectType, 'missing', $subject, $label, note: 'Absent from '.$subject->import_missing_runs.' consecutive runs.');
                    $context->review(ImportReviewKind::Missing, $subjectType.':'.$subject->getKey(), $label, [
                        'runs' => (int) $subject->import_missing_runs,
                        'published' => (bool) $subject->is_published,
                    ], $subject);
                });
        }

        $context->increment($subjectType === FieldProvenance::SUBJECT_DOCTOR ? 'doctors_absent' : 'facilities_absent', count($absent));
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
