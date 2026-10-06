<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ReviewResponseSource;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published review of the doctor's own profile, for „Мој профил“. The
 * reviewer appears only as everyone sees them (public name); the reply
 * carries its moderation state, which only this doctor and staff see.
 *
 * @mixin Review
 */
class DoctorDashboardReviewResource extends JsonResource
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
            'helpful_count' => (int) $this->helpful_count,
            'reply' => $this->hasResponse() ? [
                'body' => (string) $this->response_body,
                'source' => ($this->response_source ?? ReviewResponseSource::Staff)->value,
                'status' => $this->response_status?->value,
                'responded_at' => $this->response_at?->toIso8601String(),
                // Only the doctor's own reply carries staff's reason; a staff
                // response has none to show.
                'rejection_note' => $this->hasDoctorReply() ? $this->response_rejection_note : null,
            ] : null,
            // A staff-entered response is not the doctor's to change.
            'can_reply' => ! $this->hasResponse() || $this->hasDoctorReply(),
        ];
    }
}
