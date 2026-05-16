<?php

namespace App\Filament\Resources\ClinicalInterests\Pages;

use App\Filament\Resources\ClinicalInterests\ClinicalInterestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditClinicalInterest extends EditRecord
{
    protected static string $resource = ClinicalInterestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
