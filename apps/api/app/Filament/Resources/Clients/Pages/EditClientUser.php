<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientUserResource;
use App\Filament\Support\SetPasswordAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditClientUser extends EditRecord
{
    protected static string $resource = ClientUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SetPasswordAction::make(),
            // Restored from the retired generic Users resource. Without it an
            // administrator has no way to remove an account at all, which a
            // platform handling erasure requests cannot do without.
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
