<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ForumTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ForumTopic
 */
class MyForumTopicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'status' => $this->status->value,
            'rejection_note' => $this->rejection_note,
            'replies_count' => $this->replies_count,
            'last_post_at' => $this->last_post_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'category' => [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
