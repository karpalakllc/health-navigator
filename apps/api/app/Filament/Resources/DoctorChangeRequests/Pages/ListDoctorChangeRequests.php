<?php

namespace App\Filament\Resources\DoctorChangeRequests\Pages;

use App\Filament\Resources\DoctorChangeRequests\DoctorChangeRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListDoctorChangeRequests extends ListRecords
{
    protected static string $resource = DoctorChangeRequestResource::class;
}
