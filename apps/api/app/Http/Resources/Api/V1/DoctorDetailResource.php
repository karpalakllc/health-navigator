<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Doctor;
use App\Support\ReviewSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Doctor
 */
class DoctorDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'full_name' => $this->full_name,
            'title' => $this->title,
            'subspecialty' => $this->subspecialty,
            'bio' => $this->bio,
            'years_experience' => $this->years_experience,
            'education' => $this->education,
            'languages' => $this->languages ?? [],
            'clinical_interests' => $this->clinical_interests ?? [],
            'procedures' => $this->procedures ?? [],
            'consultation_fee_note' => $this->consultation_fee_note,
            'avatar_url' => $this->avatar_url,
            'office_hours' => $this->office_hours ?? [],
            'accepts_new_patients' => (bool) $this->accepts_new_patients,
            'is_featured' => (bool) $this->is_featured,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'specialties' => $this->specialties
                ->sortByDesc(fn ($specialty) => $specialty->pivot->is_primary)
                ->values()
                ->map(fn ($specialty) => [
                    'slug' => $specialty->slug,
                    'name' => $specialty->name,
                    'is_primary' => (bool) $specialty->pivot->is_primary,
                ]),
            'facilities' => $this->facilities
                ->sortByDesc(fn ($facility) => $facility->pivot->is_primary)
                ->values()
                ->map(fn ($facility) => [
                    'slug' => $facility->slug,
                    'name' => $facility->name,
                    'type' => $facility->type->value,
                    'city' => $facility->city,
                    'is_primary' => (bool) $facility->pivot->is_primary,
                ]),
            'review_summary' => ReviewSummary::for($this->resource),
        ];
    }
}
