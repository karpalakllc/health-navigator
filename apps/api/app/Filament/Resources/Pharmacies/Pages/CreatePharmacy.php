<?php

namespace App\Filament\Resources\Pharmacies\Pages;

use App\Filament\Resources\Pharmacies\Concerns\SetsPharmacyDefaults;
use App\Filament\Resources\Pharmacies\PharmacyResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePharmacy extends CreateRecord
{
    use SetsPharmacyDefaults;

    protected static string $resource = PharmacyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->applyPharmacyDefaults($data);
    }
}
