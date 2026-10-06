<?php

namespace App\Filament\Resources\DoctorChangeRequests\Pages;

use App\Filament\Resources\DoctorChangeRequests\DoctorChangeRequestResource;
use App\Filament\Support\DoctorChangeRequestActions;
use Filament\Resources\Pages\ViewRecord;

class ViewDoctorChangeRequest extends ViewRecord
{
    protected static string $resource = DoctorChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DoctorChangeRequestActions::approve(),
            DoctorChangeRequestActions::reject(),
        ];
    }
}
