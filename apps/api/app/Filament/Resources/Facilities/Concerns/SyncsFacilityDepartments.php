<?php

namespace App\Filament\Resources\Facilities\Concerns;

trait SyncsFacilityDepartments
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripDepartmentFormFields(array $data): array
    {
        unset($data['department_ids']);

        return $data;
    }

    protected function syncFacilityDepartments(): void
    {
        $state = $this->form->getState();

        $this->record->departments()->sync($state['department_ids'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillDepartmentFormFields(array $data): array
    {
        $this->record->load('departments');

        $data['department_ids'] = $this->record->departments->pluck('id')->all();

        return $data;
    }
}
