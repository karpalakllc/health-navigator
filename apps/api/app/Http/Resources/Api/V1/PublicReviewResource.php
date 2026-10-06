<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ReviewResponseSource;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\ReviewAspectRating;
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
            'author_name' => $this->user->publicName(),
            'published_at' => $this->published_at?->toIso8601String(),
            'response' => $this->officialResponse(),
            'helpful_count' => (int) $this->helpful_count,
            // Optional sub-ratings by aspect code (W5-I), when the list loaded them.
            'aspects' => $this->whenLoaded('aspectRatings', fn (): object => (object) $this->aspectRatings
                ->mapWithKeys(fn (ReviewAspectRating $aspect): array => [$aspect->aspect->value => $aspect->rating])
                ->all()),
            // Only on a signed-in request whose controller resolved it; absent
            // for anonymous visitors so their payload is the same for everyone.
            'viewer' => $this->when(
                $request->user() !== null && $this->viewerHasVotedHelpful !== null,
                fn (): array => ['has_voted_helpful' => (bool) $this->viewerHasVotedHelpful],
            ),
        ];
    }

    /**
     * The reviewed doctor's or facility's reply, or null: entered by staff on
     * their behalf (`source` staff) or written by the linked doctor (`source`
     * doctor, shown only once approved). Plain text; clients render it as text.
     *
     * @return array{body: string, responder_name: string|null, responded_at: string|null, source: string}|null
     */
    private function officialResponse(): ?array
    {
        if (! $this->hasPublicResponse()) {
            return null;
        }

        $reviewable = $this->reviewable;

        return [
            'body' => (string) $this->response_body,
            'responder_name' => match (true) {
                $reviewable instanceof Doctor => $reviewable->full_name,
                $reviewable instanceof Facility => $reviewable->name,
                default => null,
            },
            'responded_at' => $this->response_at?->toIso8601String(),
            'source' => ($this->response_source ?? ReviewResponseSource::Staff)->value,
        ];
    }
}
