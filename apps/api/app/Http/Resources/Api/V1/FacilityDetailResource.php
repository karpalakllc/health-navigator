<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Facility;
use App\Support\ReviewSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Facility
 */
class FacilityDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type->value,
            'description' => $this->description,
            'city' => $this->city,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'avatar_url' => $this->avatar_url,
            'office_hours' => $this->office_hours ?? [],
            'doctors' => $this->doctors
                ->sortByDesc(fn ($doctor) => $doctor->pivot->is_primary)
                ->values()
                ->map(fn ($doctor) => [
                    'slug' => $doctor->slug,
                    'full_name' => $doctor->full_name,
                    'title' => $doctor->title,
                    'is_primary' => (bool) $doctor->pivot->is_primary,
                ]),
            'review_summary' => ReviewSummary::for($this->resource),
        ];
    }
}
