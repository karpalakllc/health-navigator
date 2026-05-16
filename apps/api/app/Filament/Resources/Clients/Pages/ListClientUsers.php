<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientUserResource;
use Filament\Resources\Pages\ListRecords;

class ListClientUsers extends ListRecords
{
    protected static string $resource = ClientUserResource::class;
}
