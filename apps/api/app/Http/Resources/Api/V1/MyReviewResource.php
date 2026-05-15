<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class MyReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reviewable = $this->reviewable;

        $target = match (true) {
            $reviewable instanceof Doctor => [
                'kind' => 'doctor',
                'slug' => $reviewable->slug,
                'name' => $reviewable->full_name,
            ],
            $reviewable instanceof Facility => [
                'kind' => 'facility',
                'slug' => $reviewable->slug,
                'name' => $reviewable->name,
            ],
            default => null,
        };

        return [
            'rating' => $this->rating,
            'body' => $this->body,
            'status' => $this->status->value,
            'reviewable' => $target,
            'created_at' => $this->created_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
