<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\ReviewAspectRating;
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
            // The author's own review only: why it was refused, and whether
            // they may still edit it and send it once more.
            'rejection_note' => $this->status === ReviewStatus::Rejected ? $this->rejection_note : null,
            'removed' => $this->isRemoved(),
            'can_resubmit' => $this->canBeResubmitted(),
            'aspects' => (object) $this->aspectRatings
                ->mapWithKeys(fn (ReviewAspectRating $aspect): array => [$aspect->aspect->value => $aspect->rating])
                ->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
