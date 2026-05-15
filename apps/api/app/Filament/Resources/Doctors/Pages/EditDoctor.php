<?php

namespace App\Filament\Resources\Doctors\Pages;

use App\Filament\Concerns\HasPublicPreviewAction;
use App\Filament\Resources\Doctors\Concerns\SyncsDoctorFacilities;
use App\Filament\Resources\Doctors\Concerns\SyncsDoctorSpecialties;
use App\Filament\Resources\Doctors\DoctorResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDoctor extends EditRecord
{
    use HasPublicPreviewAction;
    use SyncsDoctorFacilities;
    use SyncsDoctorSpecialties;

    protected static string $resource = DoctorResource::class;

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
        $data = $this->fillSpecialtyFormFields($data);

        return $this->fillFacilityFormFields($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->stripSpecialtyFormFields($data);

        return $this->stripFacilityFormFields($data);
    }

    protected function afterSave(): void
    {
        $this->syncDoctorSpecialties();
        $this->syncDoctorFacilities();
    }
}
