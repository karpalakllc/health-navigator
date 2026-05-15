<?php

namespace App\Filament\Resources\Facilities\Pages;

use App\Filament\Resources\Facilities\Concerns\SyncsFacilityDoctors;
use App\Filament\Resources\Facilities\FacilityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFacility extends CreateRecord
{
    use SyncsFacilityDoctors;

    protected static string $resource = FacilityResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->stripDoctorFormFields($data);
    }

    protected function afterCreate(): void
    {
        $this->syncFacilityDoctors();
    }
}
