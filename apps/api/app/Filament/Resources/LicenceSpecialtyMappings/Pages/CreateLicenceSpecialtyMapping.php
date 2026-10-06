<?php

namespace App\Filament\Resources\LicenceSpecialtyMappings\Pages;

use App\Filament\Resources\LicenceSpecialtyMappings\LicenceSpecialtyMappingResource;
use App\Models\LicenceSpecialtyMapping;
use Filament\Resources\Pages\CreateRecord;

class CreateLicenceSpecialtyMapping extends CreateRecord
{
    protected static string $resource = LicenceSpecialtyMappingResource::class;

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record instanceof LicenceSpecialtyMapping) {
            EditLicenceSpecialtyMapping::markReviewed($record);
        }
    }
}
