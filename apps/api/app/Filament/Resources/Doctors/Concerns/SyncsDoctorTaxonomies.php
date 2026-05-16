<?php

namespace App\Filament\Resources\Doctors\Concerns;

trait SyncsDoctorTaxonomies
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripTaxonomyFormFields(array $data): array
    {
        unset(
            $data['language_ids'],
            $data['clinical_interest_ids'],
            $data['procedure_ids'],
        );

        return $data;
    }

    protected function syncDoctorTaxonomies(): void
    {
        $state = $this->form->getState();

        $this->record->languages()->sync($state['language_ids'] ?? []);
        $this->record->clinicalInterests()->sync($state['clinical_interest_ids'] ?? []);
        $this->record->procedures()->sync($state['procedure_ids'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillTaxonomyFormFields(array $data): array
    {
        $this->record->load(['languages', 'clinicalInterests', 'procedures']);

        $data['language_ids'] = $this->record->languages->pluck('id')->all();
        $data['clinical_interest_ids'] = $this->record->clinicalInterests->pluck('id')->all();
        $data['procedure_ids'] = $this->record->procedures->pluck('id')->all();

        return $data;
    }
}
