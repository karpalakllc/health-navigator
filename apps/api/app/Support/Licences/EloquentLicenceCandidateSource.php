<?php

namespace App\Support\Licences;

use App\Support\Import\NameKey;
use App\Support\Licences\Contracts\LicenceCandidate;
use App\Support\Licences\Contracts\LicenceCandidateSource;
use Illuminate\Support\Facades\DB;

/**
 * Candidates from the doctors table, on the columns the import core (W6-A)
 * adds: name_key / name_key_sorted (NameKey of full_name), licence_number,
 * import_source.
 *
 * - Profiles the ФЗОМ import created (config licences.match.imported_source;
 *   null considers every profile) — a licence is evidence about a doctor we
 *   know works somewhere, not a reason to touch a hand-made profile.
 * - Then, as fallback candidates, drafts an institution's own staff page
 *   created (config licences.match.fallback_sources) without a ФЗО
 *   facsimile: the matcher only looks at them for a name no ФЗОМ profile
 *   carries.
 * - Dentists are left out: their licences are not on the Комора list, and
 *   the import files them under specialties whose slug starts with
 *   config licences.match.dental_slug_prefix.
 * - Soft-deleted profiles are left out.
 */
final class EloquentLicenceCandidateSource implements LicenceCandidateSource
{
    public function doctorIdForLicence(string $licenceNumber): ?int
    {
        $id = DB::table('doctors')
            ->whereNull('deleted_at')
            ->where('licence_number', $licenceNumber)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function candidatesFor(string $fullName): array
    {
        $key = NameKey::for($fullName);

        if ($key === '') {
            return [];
        }

        $importedSource = config('licences.match.imported_source');
        $restricted = is_string($importedSource) && $importedSource !== '';
        $fallbackSources = array_values(array_diff(array_map('strval', (array) config('licences.match.fallback_sources', [])), [$importedSource]));

        $doctors = DB::table('doctors')
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->where('name_key', $key)->orWhere('name_key_sorted', NameKey::sorted($fullName)))
            ->when($restricted, fn ($query) => $query->where(fn ($query) => $query
                ->where('import_source', $importedSource)
                ->when($fallbackSources !== [], fn ($query) => $query->orWhere(fn ($query) => $query
                    ->whereIn('import_source', $fallbackSources)
                    ->whereNull('fzo_facsimile')))))
            ->orderBy('id')
            ->get(['id', 'licence_number', 'import_source']);

        if ($doctors->isEmpty()) {
            return [];
        }

        $specialties = DB::table('doctor_specialty')
            ->join('specialties', 'specialties.id', '=', 'doctor_specialty.specialty_id')
            ->whereIn('doctor_specialty.doctor_id', $doctors->pluck('id'))
            ->whereNull('specialties.deleted_at')
            ->get(['doctor_specialty.doctor_id', 'specialties.id', 'specialties.name', 'specialties.slug'])
            ->groupBy('doctor_id');

        $dentalPrefix = (string) config('licences.match.dental_slug_prefix', 'stomatologija');
        $candidates = [];

        foreach ($doctors as $doctor) {
            $own = $specialties->get($doctor->id, collect());
            $slugs = $own->pluck('slug')->map(fn ($slug): string => (string) $slug)->values()->all();

            if ($slugs !== [] && $dentalPrefix !== '' && array_filter($slugs, fn (string $slug): bool => ! str_starts_with($slug, $dentalPrefix)) === []) {
                continue;
            }

            $candidates[] = new LicenceCandidate(
                doctorId: (int) $doctor->id,
                licenceNumber: $doctor->licence_number === null ? null : (string) $doctor->licence_number,
                specialtyIds: $own->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                specialtyNames: $own->pluck('name')->map(fn ($name): string => (string) $name)->values()->all(),
                specialtySlugs: $slugs,
                fallback: $restricted && $doctor->import_source !== $importedSource,
            );
        }

        return $candidates;
    }
}
