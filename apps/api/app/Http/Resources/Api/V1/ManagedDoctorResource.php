<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Doctor;
use App\Support\DoctorAccount\DoctorProfileFields;
use App\Support\Media\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The profile as its linked doctor edits it (GET /me/doctor): current values
 * with ids for the linked taxonomies, and whether it is public. Never the
 * featured/sponsored flags' controls — those are staff decisions.
 *
 * @mixin Doctor
 */
class ManagedDoctorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sensitive = DoctorProfileFields::sensitiveSnapshot($this->resource);
        $this->resource->loadMissing(['languages', 'clinicalInterests', 'procedures']);

        return [
            'slug' => $this->slug,
            'is_published' => (bool) $this->is_published,
            'full_name' => $this->full_name,
            'title' => $this->title,
            'subspecialty' => $this->subspecialty,
            'education' => $this->education,
            'years_experience' => $this->years_experience,
            'city' => $this->city,
            'bio' => $this->bio,
            'phone' => $this->phone,
            'email' => $this->email,
            'consultation_fee_note' => $this->consultation_fee_note,
            'accepts_new_patients' => (bool) $this->accepts_new_patients,
            'office_hours' => (object) ($this->office_hours ?? []),
            'avatar_url' => MediaUrl::resolve($this->avatar_url),
            'specialties' => $sensitive['specialties'],
            'facilities' => $sensitive['facilities'],
            'language_ids' => $this->languages->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'clinical_interest_ids' => $this->clinicalInterests->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'procedure_ids' => $this->procedures->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'linked_at' => $this->owner_linked_at?->toIso8601String(),
        ];
    }
}
