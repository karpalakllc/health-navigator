<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ForumTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ForumTopic
 */
class ForumTopicListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'author_name' => $this->user->name,
            'replies_count' => $this->replies_count,
            'last_post_at' => $this->last_post_at?->toIso8601String(),
            'is_pinned' => $this->is_pinned,
            'is_locked' => $this->is_locked,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
