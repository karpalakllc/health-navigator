<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientUserResource;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditClientUser extends EditRecord
{
    protected static string $resource = ClientUserResource::class;

    protected function afterSave(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
