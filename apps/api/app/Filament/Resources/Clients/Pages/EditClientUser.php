<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientUserResource;
use App\Filament\Support\RenameUsernameAction;
use App\Filament\Support\SetPasswordAction;
use App\Filament\Support\SuspendAccountActions;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditClientUser extends EditRecord
{
    protected static string $resource = ClientUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SetPasswordAction::make(),
            RenameUsernameAction::make(),
            SuspendAccountActions::suspend(),
            SuspendAccountActions::unsuspend(),
            // No DeleteAction: UserPolicy::delete refuses clients because their
            // reviews and forum content reference them with restrictOnDelete.
            // Members erase their own account by anonymisation
            // (DELETE /api/v1/me, App\Actions\AnonymiseUser).
        ];
    }

    protected function afterSave(): void
    {
        // Same as SetPasswordAction: an admin-set password ends existing API sessions.
        if ($this->record->wasChanged('password')) {
            $this->record->revokeApiTokens();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Scoped-category assignment may have just changed; drop the memo so any
        // authorization check later in this request sees the new scope.
        $this->record->forgetForumModerationScope();
    }
}
