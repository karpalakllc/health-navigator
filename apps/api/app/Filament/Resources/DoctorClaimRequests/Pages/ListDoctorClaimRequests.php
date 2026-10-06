<?php

namespace App\Filament\Resources\DoctorClaimRequests\Pages;

use App\Filament\Resources\DoctorClaimRequests\DoctorClaimRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListDoctorClaimRequests extends ListRecords
{
    protected static string $resource = DoctorClaimRequestResource::class;
}
