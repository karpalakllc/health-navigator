<?php

namespace App\Filament\Resources\Pharmacies\Pages;

use App\Filament\Concerns\HasPublicPreviewAction;
use App\Filament\Resources\Pharmacies\Concerns\SetsPharmacyDefaults;
use App\Filament\Resources\Pharmacies\PharmacyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPharmacy extends EditRecord
{
    use HasPublicPreviewAction;
    use SetsPharmacyDefaults;

    protected static string $resource = PharmacyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getPublicPreviewAction(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->applyPharmacyDefaults($data);
    }
}
