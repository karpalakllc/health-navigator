<?php

namespace App\Filament\Resources\Facilities\Concerns;

trait SyncsFacilityDoctors
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripDoctorFormFields(array $data): array
    {
        unset($data['doctor_ids'], $data['primary_doctor_id']);

        if (! empty($data['is_published']) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function syncFacilityDoctors(): void
    {
        $state = $this->form->getState();
        $ids = $state['doctor_ids'] ?? [];
        $primaryId = $state['primary_doctor_id'] ?? null;

        $sync = [];

        foreach ($ids as $id) {
            $sync[$id] = ['is_primary' => (int) $id === (int) $primaryId];
        }

        $this->record->doctors()->sync($sync);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillDoctorFormFields(array $data): array
    {
        $this->record->load('doctors');

        $data['doctor_ids'] = $this->record->doctors->pluck('id')->all();
        $data['primary_doctor_id'] = $this->record->doctors
            ->first(fn ($doctor) => $doctor->pivot->is_primary)
            ?->id;

        return $data;
    }
}
