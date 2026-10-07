<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ReviewStatus;
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
                'kind' => $reviewable->isPharmacy() ? 'pharmacy' : 'facility',
                'slug' => $reviewable->slug,
                'name' => $reviewable->name,
            ],
            default => null,
        };

        $published = $this->status === ReviewStatus::Approved;

        return [
            'id' => $this->getKey(),
            'rating' => $this->rating,
            'body' => $this->body,
            'status' => $this->status->value,
            'rejection_note' => $this->rejection_note,
            'can_resubmit' => $this->canBeResubmitted(),
            'reviewable' => $target,
            'created_at' => $this->created_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            // W8-B impact, for the author only: how often the card was on a
            // visitor's screen (ReviewViews), „Корисно“ votes, and the
            // profile's reply once it is public (a pending one is not shown).
            'impact' => $published ? [
                'views' => (int) $this->view_count,
                'helpful' => (int) $this->helpful_count,
            ] : null,
            'reply' => $published && $this->resource->hasPublicResponse() ? [
                'body' => $this->response_body,
                'source' => $this->response_source?->value,
                'responded_at' => $this->response_at?->toIso8601String(),
            ] : null,
        ];
    }
}
