<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class PublicReviewResource extends JsonResource
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
            'author_name' => $this->user->name,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
