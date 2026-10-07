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
class PharmacyListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'city' => $this->city,
            'avatar_url' => MediaUrl::resolve($this->avatar_url),
            'cover_url' => MediaUrl::resolve($this->cover_path),
            // Columns of the row already loaded: the card's „Јави се“ and
            // open-now line cost no extra query.
            'phone' => $this->phone,
            'office_hours' => $this->office_hours ?? [],
            'is_featured' => (bool) $this->is_featured,
            // „Верификуван“ / „Неверификуван“: status and public basis label
            // only — the evidence behind it stays internal.
            'verification' => $this->publicVerification(),
            'review_summary' => ReviewSummary::for($this->resource),
        ];
    }
}
