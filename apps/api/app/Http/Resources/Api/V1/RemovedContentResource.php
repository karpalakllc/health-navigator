<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ForumPost;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public placeholder of a review or forum reply that was published and
 * later removed: when, and the public category. Deliberately nothing else —
 * no text, rating, author or moderator note — so the trace is visible without
 * republishing what was taken down.
 *
 * @mixin Review|ForumPost
 */
class RemovedContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'removed' => true,
            'removed_at' => $this->removed_at?->toIso8601String(),
            'removal_category' => $this->removal_category?->value,
        ];
    }
}
