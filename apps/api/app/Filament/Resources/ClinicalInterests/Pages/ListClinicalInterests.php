<?php

namespace App\Filament\Resources\ClinicalInterests\Pages;

use App\Filament\Resources\ClinicalInterests\ClinicalInterestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClinicalInterests extends ListRecords
{
    protected static string $resource = ClinicalInterestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
