<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffUserResource;
use App\Filament\Support\SetPasswordAction;
use Filament\Resources\Pages\EditRecord;

class EditStaffUser extends EditRecord
{
    protected static string $resource = StaffUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SetPasswordAction::make(),
        ];
    }
}
