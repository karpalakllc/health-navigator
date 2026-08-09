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
class FacilityListResource extends JsonResource
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
            'city' => $this->city,
            'avatar_url' => MediaUrl::resolve($this->avatar_url),
            'has_emergency_services' => (bool) $this->has_emergency_services,
            'is_featured' => (bool) $this->is_featured,
            'departments_count' => (int) ($this->departments_count ?? 0),
            'review_summary' => ReviewSummary::for($this->resource),
        ];
    }
}
