<?php

namespace App\Filament\Resources\Doctors\Pages;

use App\Filament\Resources\Doctors\Concerns\SyncsDoctorFacilities;
use App\Filament\Resources\Doctors\Concerns\SyncsDoctorSpecialties;
use App\Filament\Resources\Doctors\Concerns\SyncsDoctorTaxonomies;
use App\Filament\Resources\Doctors\DoctorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDoctor extends CreateRecord
{
    use SyncsDoctorFacilities;
    use SyncsDoctorSpecialties;
    use SyncsDoctorTaxonomies;

    protected static string $resource = DoctorResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->stripSpecialtyFormFields($data);
        $data = $this->stripTaxonomyFormFields($data);

        return $this->stripFacilityFormFields($data);
    }

    protected function afterCreate(): void
    {
        $this->syncDoctorSpecialties();
        $this->syncDoctorFacilities();
        $this->syncDoctorTaxonomies();
    }
}
