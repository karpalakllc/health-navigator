<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Facility;
use App\Support\Media\MediaUrl;
use App\Support\ReviewSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Facility
 */
class PharmacyDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'city' => $this->city,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'avatar_url' => MediaUrl::resolve($this->avatar_url),
            'cover_url' => MediaUrl::resolve($this->cover_path),
            'is_featured' => (bool) $this->is_featured,
            // „Верифициран“ / „Неверифициран“: status and public basis label
            // only — the evidence behind it stays internal.
            'verification' => $this->publicVerification(),
            'office_hours' => $this->office_hours ?? [],
            'review_summary' => ReviewSummary::for($this->resource),
        ];
    }
}
