<?php

namespace App\Filament\Resources\Doctors\Concerns;

trait SyncsDoctorFacilities
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripFacilityFormFields(array $data): array
    {
        unset($data['facility_ids'], $data['primary_facility_id']);

        return $data;
    }

    protected function syncDoctorFacilities(): void
    {
        $state = $this->form->getState();
        $ids = $state['facility_ids'] ?? [];
        $primaryId = $state['primary_facility_id'] ?? null;

        $sync = [];

        foreach ($ids as $id) {
            $sync[$id] = ['is_primary' => (int) $id === (int) $primaryId];
        }

        $this->record->facilities()->sync($sync);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillFacilityFormFields(array $data): array
    {
        $this->record->load(['facilities' => fn ($query) => $query->clinical()]);

        $data['facility_ids'] = $this->record->facilities->pluck('id')->all();
        $data['primary_facility_id'] = $this->record->facilities
            ->first(fn ($facility) => $facility->pivot->is_primary)
            ?->id;

        return $data;
    }
}
