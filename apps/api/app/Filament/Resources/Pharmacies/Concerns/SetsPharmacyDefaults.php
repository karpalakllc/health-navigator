<?php

namespace App\Filament\Resources\Pharmacies\Concerns;

use App\Enums\FacilityType;

trait SetsPharmacyDefaults
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function applyPharmacyDefaults(array $data): array
    {
        $data['type'] = FacilityType::Pharmacy->value;
        $data['has_emergency_services'] = false;

        if (! empty($data['is_published']) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
