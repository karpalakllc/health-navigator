<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\UserKind;
use App\Filament\Resources\Staff\StaffUserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaffUser extends CreateRecord
{
    protected static string $resource = StaffUserResource::class;

    /**
     * Staff are verified from creation: nobody signs up for these accounts, so
     * nobody would ever click a verification link — and an unverified account is
     * what the public sign-up treats as still pending. user_kind is not fillable,
     * so it is force-filled here rather than passed through the form data.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $record = new ($this->getModel());
        $record->forceFill([
            ...$data,
            'user_kind' => UserKind::Staff,
            'email_verified_at' => now(),
        ])->save();

        return $record;
    }
}
