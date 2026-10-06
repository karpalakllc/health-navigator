<?php

namespace App\Filament\Resources\LicenceSpecialtyMappings\Pages;

use App\Filament\Resources\LicenceSpecialtyMappings\LicenceSpecialtyMappingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLicenceSpecialtyMappings extends ListRecords
{
    protected static string $resource = LicenceSpecialtyMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
