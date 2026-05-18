<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ViewerReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->body,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
