<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Filament\Resources\Staff\StaffUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaffUser extends CreateRecord
{
    protected static string $resource = StaffUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_kind'] = UserKind::Staff;

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->roles()->exists()) {
            return;
        }

        $roleName = match ($this->record->role) {
            UserRole::Admin => 'Administrator',
            UserRole::Moderator => 'Moderator',
            default => null,
        };

        if ($roleName !== null) {
            $this->record->assignRole($roleName);
        }
    }
}
