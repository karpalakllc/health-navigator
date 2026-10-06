<?php

namespace App\Support\Import;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\SourceRecord;
use App\Support\Slug;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * The writes every directory importer shares: hidden drafts, the doctor ↔
 * specialty and doctor ↔ facility links an import owns, and source records.
 *
 * Link ownership: a pivot row carries the source that created it. An import
 * adds and removes only its own rows; a link staff made (source null) is
 * never removed or taken over, and a locked "specialties" / "facilities"
 * field is not touched at all.
 */
final class DirectoryWriter
{
    public function __construct(
        private readonly ImportContext $context,
        private readonly ProvenanceWriter $provenance,
    ) {}

    public function provenance(): ProvenanceWriter
    {
        return $this->provenance;
    }

    /**
     * A new, unpublished doctor. Never published here: staff publish from the
     * review queue.
     */
    public function newDoctor(string $fullName, ?string $city): Doctor
    {
        $doctor = new Doctor;
        $doctor->forceFill([
            'slug' => $this->uniqueSlug(Doctor::class, $fullName, $city),
            'is_published' => false,
            'published_at' => null,
            'import_source' => $this->context->source,
        ]);

        return $doctor;
    }

    public function newFacility(string $name, ?string $city, FacilityType $type): Facility
    {
        $facility = new Facility;
        $facility->forceFill([
            'slug' => $this->uniqueSlug(Facility::class, $name, $city),
            'type' => $type,
            'is_published' => false,
            'published_at' => null,
            'import_source' => $this->context->source,
        ]);

        return $facility;
    }

    /**
     * Saves a doctor or facility, and the provenance of a freshly created one
     * (Doctor keeps its own name keys current).
     */
    public function save(Doctor|Facility $subject): void
    {
        $isNew = ! $subject->exists;

        $subject->save();

        if ($isNew) {
            $this->provenance->rememberAfterCreate($subject);
        }
    }

    /**
     * Makes the doctor's import-owned specialty links equal $specialtyIds.
     *
     * @param  list<int>  $specialtyIds  first = primary candidate
     * @return bool whether anything changed
     */
    public function syncSpecialties(Doctor $doctor, array $specialtyIds, string $label, bool $isNew): bool
    {
        if (! $isNew && $this->provenance->isLocked($doctor, 'specialties')) {
            return false;
        }

        $existing = DB::table('doctor_specialty')->where('doctor_id', $doctor->getKey())
            ->get(['specialty_id', 'source', 'is_primary'])
            ->keyBy(fn ($row): int => (int) $row->specialty_id);

        $owned = $existing->filter(fn ($row): bool => $row->source === $this->context->source)->keys()->all();
        $add = array_values(array_diff($specialtyIds, $existing->keys()->all()));
        $remove = array_values(array_diff($owned, $specialtyIds));
        $hasPrimary = $existing->contains(fn ($row): bool => (bool) $row->is_primary && ! in_array((int) $row->specialty_id, $remove, true));
        $now = now();

        foreach ($add as $specialtyId) {
            DB::table('doctor_specialty')->insert([
                'doctor_id' => $doctor->getKey(),
                'specialty_id' => $specialtyId,
                'is_primary' => false,
                'source' => $this->context->source,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($remove !== []) {
            DB::table('doctor_specialty')->where('doctor_id', $doctor->getKey())->whereIn('specialty_id', $remove)->delete();
        }

        // Only when nobody (staff included) chose a primary specialty.
        if (! $hasPrimary && $specialtyIds !== []) {
            DB::table('doctor_specialty')->where('doctor_id', $doctor->getKey())->where('specialty_id', $specialtyIds[0])->update(['is_primary' => true]);
        }

        $this->provenance->relation($doctor, 'specialties', $specialtyIds);
        $changed = $add !== [] || $remove !== [];

        if ($changed && ! $isNew) {
            $before = $existing->keys()->sort()->implode(',');
            $after = collect($existing->keys()->all())->diff($remove)->merge($add)->sort()->implode(',');
            $this->context->increment('relations_updated');
            $this->context->record('doctor', 'update', $doctor, $label, 'specialties', $before, $after);
            $this->provenance->noteChange($doctor, $label, 'specialties', $before, $after);
        }

        return $changed;
    }

    /**
     * Makes the doctor's import-owned facility links equal $links.
     *
     * @param  array<int, array{work_unit: string|null, contract_type: string|null}>  $links  facility id => contract
     */
    public function syncFacilities(Doctor $doctor, array $links, ?int $primaryFacilityId, string $label, bool $isNew): bool
    {
        if (! $isNew && $this->provenance->isLocked($doctor, 'facilities')) {
            return false;
        }

        $existing = DB::table('doctor_facility')->where('doctor_id', $doctor->getKey())
            ->get(['facility_id', 'source', 'is_primary', 'work_unit', 'contract_type'])
            ->keyBy(fn ($row): int => (int) $row->facility_id);

        $wanted = array_keys($links);
        $owned = $existing->filter(fn ($row): bool => $row->source === $this->context->source)->keys()->all();
        $add = array_values(array_diff($wanted, $existing->keys()->all()));
        $remove = array_values(array_diff($owned, $wanted));
        $hasPrimary = $existing->contains(fn ($row): bool => (bool) $row->is_primary && ! in_array((int) $row->facility_id, $remove, true));
        $now = now();

        foreach ($add as $facilityId) {
            DB::table('doctor_facility')->insert([
                'doctor_id' => $doctor->getKey(),
                'facility_id' => $facilityId,
                'is_primary' => false,
                'source' => $this->context->source,
                'work_unit' => $links[$facilityId]['work_unit'],
                'contract_type' => $links[$facilityId]['contract_type'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($owned as $facilityId) {
            if (! isset($links[$facilityId])) {
                continue;
            }

            $row = $existing[$facilityId];

            if ($row->work_unit !== $links[$facilityId]['work_unit'] || $row->contract_type !== $links[$facilityId]['contract_type']) {
                DB::table('doctor_facility')->where('doctor_id', $doctor->getKey())->where('facility_id', $facilityId)
                    ->update(['work_unit' => $links[$facilityId]['work_unit'], 'contract_type' => $links[$facilityId]['contract_type'], 'updated_at' => $now]);
            }
        }

        if ($remove !== []) {
            DB::table('doctor_facility')->where('doctor_id', $doctor->getKey())->whereIn('facility_id', $remove)->delete();
        }

        if (! $hasPrimary && $primaryFacilityId !== null && in_array($primaryFacilityId, $wanted, true)) {
            DB::table('doctor_facility')->where('doctor_id', $doctor->getKey())->where('facility_id', $primaryFacilityId)->update(['is_primary' => true]);
        }

        $this->provenance->relation($doctor, 'facilities', $wanted);
        $changed = $add !== [] || $remove !== [];

        if ($changed && ! $isNew) {
            $before = $existing->keys()->sort()->implode(',');
            $after = collect($existing->keys()->all())->diff($remove)->merge($add)->sort()->implode(',');
            $this->context->increment('relations_updated');
            $this->context->record('doctor', 'update', $doctor, $label, 'facilities', $before, $after);
            $this->provenance->noteChange($doctor, $label, 'facilities', $before, $after);
        }

        return $changed;
    }

    /**
     * The stored source record for a key, created or refreshed.
     *
     * @param  array<string, mixed>  $payload
     * @return array{record: SourceRecord, unchanged: bool}
     */
    public function sourceRecord(string $externalKey, string $subjectType, array $payload, ?SourceRecord $existing): array
    {
        $hash = hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE));
        $now = now();

        if ($existing !== null && $existing->hash === $hash) {
            return ['record' => $existing, 'unchanged' => true];
        }

        $record = $existing ?? new SourceRecord([
            'source' => $this->context->source,
            'external_key' => $externalKey,
            'subject_type' => $subjectType,
            'first_seen_at' => $now,
        ]);

        $record->fill([
            'payload' => $payload,
            'hash' => $hash,
            'last_seen_at' => $now,
            'last_run_id' => $this->context->run->getKey(),
        ])->save();

        return ['record' => $record, 'unchanged' => false];
    }

    /**
     * @param  class-string<Doctor|Facility>  $model
     */
    private function uniqueSlug(string $model, string $name, ?string $city): string
    {
        $base = Slug::fromName($name, 'profil');
        $query = $model::withTrashed();

        if (! $query->clone()->where('slug', $base)->exists()) {
            return $base;
        }

        // A town reads better than a number: "ana-petrova-bitola".
        if ($city !== null && trim($city) !== '') {
            $withCity = $base.'-'.Slug::fromName($city, 'grad');

            if (! $query->clone()->where('slug', $withCity)->exists()) {
                return $withCity;
            }

            $base = $withCity;
        }

        return UniqueSlug::forQuery($query, $base);
    }
}
