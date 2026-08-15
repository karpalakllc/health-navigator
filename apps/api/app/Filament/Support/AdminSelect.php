<?php

namespace App\Filament\Support;

use App\Filament\Support\TaxonomyForm as TaxonomyFormFields;
use App\Models\ClinicalInterest;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Language;
use App\Models\Procedure;
use App\Models\Specialty;
use App\Support\ScriptInsensitiveSearch;
use App\Support\Slug;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

final class AdminSelect
{
    public static function affiliatedDoctors(string $name = 'doctor_ids'): Select
    {
        return Select::make($name)
            ->label('Affiliated doctors')
            ->multiple()
            ->searchable()
            ->preload()
            ->getSearchResultsUsing(fn (?string $search): array => self::searchDoctors($search))
            ->getOptionLabelsUsing(
                fn (array $values): array => Doctor::query()
                    ->whereIn('id', $values)
                    ->orderBy('full_name')
                    ->pluck('full_name', 'id')
                    ->all(),
            )
            ->columnSpanFull();
    }

    public static function affiliatedClinicalFacilities(string $name = 'facility_ids'): Select
    {
        return Select::make($name)
            ->label('Workplaces (clinical facilities)')
            ->multiple()
            ->searchable()
            ->preload()
            ->getSearchResultsUsing(fn (?string $search): array => self::searchClinicalFacilities($search))
            ->getOptionLabelsUsing(
                fn (array $values): array => Facility::query()
                    ->whereIn('id', $values)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all(),
            )
            ->columnSpanFull();
    }

    public static function doctorSpecialties(string $name = 'specialty_ids'): Select
    {
        return self::taxonomyMulti(
            $name,
            'Specialties',
            Specialty::class,
            'Create specialty',
        );
    }

    public static function doctorLanguages(string $name = 'language_ids'): Select
    {
        return self::taxonomyMulti(
            $name,
            'Languages spoken',
            Language::class,
            'Create language',
        );
    }

    public static function doctorClinicalInterests(string $name = 'clinical_interest_ids'): Select
    {
        return self::taxonomyMulti(
            $name,
            'Clinical interests / conditions',
            ClinicalInterest::class,
            'Create clinical interest',
        );
    }

    public static function doctorProcedures(string $name = 'procedure_ids'): Select
    {
        return self::taxonomyMulti(
            $name,
            'Procedures & services',
            Procedure::class,
            'Create procedure',
        );
    }

    public static function facilityDepartments(string $name = 'department_ids'): Select
    {
        return self::taxonomyMulti(
            $name,
            'Departments',
            Department::class,
            'Create department',
        );
    }

    /**
     * @param  class-string<Language|ClinicalInterest|Procedure|Specialty>  $modelClass
     */
    public static function taxonomyMulti(
        string $name,
        string $label,
        string $modelClass,
        string $createModalHeading,
    ): Select {
        /** @var Model $prototype */
        $prototype = new $modelClass;

        return Select::make($name)
            ->label($label)
            ->multiple()
            ->searchable()
            ->preload()
            ->getSearchResultsUsing(fn (?string $search): array => self::searchTaxonomy($modelClass, $search))
            ->getOptionLabelsUsing(
                fn (array $values): array => $modelClass::query()
                    ->whereIn('id', $values)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all(),
            )
            ->createOptionForm(TaxonomyFormFields::fields())
            ->createOptionModalHeading($createModalHeading)
            ->createOptionUsing(function (array $data) use ($modelClass): int {
                $record = $modelClass::query()->create([
                    'name' => $data['name'],
                    'slug' => $data['slug'] ?? Slug::fromName($data['name']),
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                    'is_published' => (bool) ($data['is_published'] ?? true),
                ]);

                return (int) $record->getKey();
            })
            ->columnSpanFull();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int|string, string>
     */
    private static function searchTaxonomy(string $modelClass, ?string $search): array
    {
        $query = $modelClass::query()->orderBy('name')->limit(50);

        if (filled($search) && method_exists($modelClass, 'scopeSearchName')) {
            $query->searchName($search);
        } elseif (filled($search)) {
            // A bare LIKE is case-sensitive on PostgreSQL, so admin pickers would not
            // match "кардио" against "Кардиологија" in production while working fine
            // against SQLite locally. Route through the same helper the model scopes use.
            ScriptInsensitiveSearch::whereColumnMatches($query, 'name', $search);
        }

        return $query->pluck('name', 'id')->all();
    }

    /**
     * @return array<int|string, string>
     */
    private static function searchDoctors(?string $search): array
    {
        $query = Doctor::query()->orderBy('full_name')->limit(50);

        if (filled($search)) {
            $query->searchName($search);
        }

        return $query->pluck('full_name', 'id')->all();
    }

    /**
     * @return array<int|string, string>
     */
    private static function searchClinicalFacilities(?string $search): array
    {
        $query = Facility::query()->clinical()->orderBy('name')->limit(50);

        if (filled($search)) {
            $query->searchName($search);
        }

        return $query->pluck('name', 'id')->all();
    }
}
