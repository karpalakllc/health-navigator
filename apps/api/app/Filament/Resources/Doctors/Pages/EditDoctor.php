<?php

namespace App\Filament\Resources\Doctors\Pages;

use App\Filament\Concerns\HasPublicPreviewAction;
use App\Filament\Resources\Doctors\Concerns\SyncsDoctorFacilities;
use App\Filament\Resources\Doctors\Concerns\SyncsDoctorSpecialties;
use App\Filament\Resources\Doctors\Concerns\SyncsDoctorTaxonomies;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Support\DoctorOwnerActions;
use App\Filament\Support\ImportLockAction;
use App\Filament\Support\VerificationActions;
use App\Models\Doctor;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDoctor extends EditRecord
{
    use HasPublicPreviewAction;
    use SyncsDoctorFacilities;
    use SyncsDoctorSpecialties;
    use SyncsDoctorTaxonomies;

    protected static string $resource = DoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getPublicPreviewAction(),
            DoctorOwnerActions::assign(),
            DoctorOwnerActions::remove(),
            ImportLockAction::make(),
            ...VerificationActions::all(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /** A licence in its grace period (LicenceGrace) is said under the title. */
    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return $record instanceof Doctor ? VerificationActions::graceNote($record) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = $this->fillSpecialtyFormFields($data);
        $data = $this->fillTaxonomyFormFields($data);

        return $this->fillFacilityFormFields($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->stripSpecialtyFormFields($data);
        $data = $this->stripTaxonomyFormFields($data);

        return $this->stripFacilityFormFields($data);
    }

    protected function afterSave(): void
    {
        $this->syncDoctorSpecialties();
        $this->syncDoctorFacilities();
        $this->syncDoctorTaxonomies();
    }
}
