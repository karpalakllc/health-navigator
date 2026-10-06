<?php

namespace App\Filament\Resources\DoctorClaimRequests\Pages;

use App\Filament\Resources\DoctorClaimRequests\DoctorClaimRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDoctorClaimRequest extends ViewRecord
{
    protected static string $resource = DoctorClaimRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DoctorClaimRequestResource::assignAction(),
            DoctorClaimRequestResource::rejectAction(),
        ];
    }
}
