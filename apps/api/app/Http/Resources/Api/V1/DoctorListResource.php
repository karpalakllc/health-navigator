<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Doctor;
use App\Support\Media\MediaUrl;
use App\Support\ReviewSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Doctor
 */
class DoctorListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primary = $this->specialties->first(
            fn ($specialty) => $specialty->pivot->is_primary,
        );

        $primaryFacility = $this->facilities->first(
            fn ($facility) => $facility->pivot->is_primary,
        ) ?? $this->facilities->first();

        return [
            'slug' => $this->slug,
            'full_name' => $this->full_name,
            'title' => $this->title,
            'subspecialty' => $this->subspecialty,
            'city' => $this->city,
            'avatar_url' => MediaUrl::resolve($this->avatar_url),
            'years_experience' => $this->years_experience,
            'accepts_new_patients' => (bool) $this->accepts_new_patients,
            'is_featured' => (bool) $this->is_featured,
            'is_sponsored' => (bool) $this->is_sponsored,
            'primary_specialty' => $primary
                ? [
                    'slug' => $primary->slug,
                    'name' => $primary->name,
                ]
                : null,
            'review_summary' => ReviewSummary::for($this->resource),
            'primary_facility' => $primaryFacility
                ? [
                    'name' => $primaryFacility->name,
                    'city' => $primaryFacility->city,
                ]
                : null,
        ];
    }
}
