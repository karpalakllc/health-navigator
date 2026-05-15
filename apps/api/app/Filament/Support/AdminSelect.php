<?php

namespace App\Filament\Support;

use App\Models\Doctor;
use App\Models\Facility;
use Filament\Forms\Components\Select;

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
