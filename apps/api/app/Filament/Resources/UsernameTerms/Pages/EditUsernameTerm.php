<?php

namespace App\Filament\Resources\UsernameTerms\Pages;

use App\Filament\Resources\UsernameTerms\UsernameTermResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUsernameTerm extends EditRecord
{
    protected static string $resource = UsernameTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
