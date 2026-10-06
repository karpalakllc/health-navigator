<?php

namespace App\Filament\Resources\DoctorReplies\Pages;

use App\Filament\Resources\DoctorReplies\DoctorReplyResource;
use Filament\Resources\Pages\ListRecords;

class ListDoctorReplies extends ListRecords
{
    protected static string $resource = DoctorReplyResource::class;
}
