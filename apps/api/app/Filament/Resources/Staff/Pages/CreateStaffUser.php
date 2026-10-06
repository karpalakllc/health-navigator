<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\UserKind;
use App\Filament\Resources\Staff\StaffUserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaffUser extends CreateRecord
{
    protected static string $resource = StaffUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_kind'] = UserKind::Staff;

        return $data;
    }

    /**
     * Staff are verified from creation: nobody signs up for these accounts, so
     * nobody would ever click a verification link — and an unverified account is
     * what the public sign-up treats as still pending.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $record = new ($this->getModel())($data);
        $record->email_verified_at = now();
        $record->save();

        return $record;
    }
}
