<?php

namespace App\Filament\Resources\Facilities\Pages;

use App\Filament\Concerns\HasPublicPreviewAction;
use App\Filament\Resources\Facilities\Concerns\SyncsFacilityDoctors;
use App\Filament\Resources\Facilities\FacilityResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFacility extends EditRecord
{
    use HasPublicPreviewAction;
    use SyncsFacilityDoctors;

    protected static string $resource = FacilityResource::class;

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
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillDoctorFormFields($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->stripDoctorFormFields($data);
    }

    protected function afterSave(): void
    {
        $this->syncFacilityDoctors();
    }
}
