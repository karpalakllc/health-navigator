<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffUserResource;
use App\Filament\Support\SetPasswordAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStaffUser extends EditRecord
{
    protected static string $resource = StaffUserResource::class;

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
}
