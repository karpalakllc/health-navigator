<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ForumPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ForumPost
 */
class MyForumPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'status' => $this->status->value,
            'topic' => [
                'slug' => $this->topic->slug,
                'title' => $this->topic->title,
                'category_slug' => $this->topic->category->slug,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
