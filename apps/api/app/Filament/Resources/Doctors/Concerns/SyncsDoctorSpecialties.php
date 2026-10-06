<?php

namespace App\Filament\Resources\Doctors\Concerns;

use App\Support\TaxonomyCache;

trait SyncsDoctorSpecialties
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripSpecialtyFormFields(array $data): array
    {
        unset($data['specialty_ids'], $data['primary_specialty_id']);

        if (! empty($data['is_published']) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function syncDoctorSpecialties(): void
    {
        $state = $this->form->getState();
        $ids = $state['specialty_ids'] ?? [];
        $primaryId = $state['primary_specialty_id'] ?? null;

        $sync = [];

        foreach ($ids as $id) {
            $sync[$id] = ['is_primary' => (int) $id === (int) $primaryId];
        }

        $this->record->specialties()->sync($sync);

        // Filament saved the doctor first (whose saved hook flushed the
        // specialty list, doctors_count included) and only then synced the
        // pivot, which fires no model event: without this the list would cache
        // the pre-sync counts for TaxonomyCache::TTL_SECONDS. Same for the
        // search document, which embeds the specialty names.
        TaxonomyCache::flush(TaxonomyCache::SPECIALTIES);
        $this->record->unsetRelation('specialties')->searchable();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillSpecialtyFormFields(array $data): array
    {
        $this->record->load('specialties');

        $data['specialty_ids'] = $this->record->specialties->pluck('id')->all();
        $data['primary_specialty_id'] = $this->record->specialties
            ->first(fn ($specialty) => $specialty->pivot->is_primary)
            ?->id;

        return $data;
    }
}
