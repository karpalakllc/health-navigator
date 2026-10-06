<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ForumPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ForumPost
 */
class ForumPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author_name' => $this->user->publicName(),
            'author' => new ForumAuthorResource($this->user),
            'published_at' => $this->published_at?->toIso8601String(),
            // Whether the topic's opener wrote this reply („Автор“ tag). Decided
            // here so the payload never has to carry an account id.
            'is_topic_author' => $this->topic !== null
                && (int) $this->user_id === (int) $this->topic->user_id,
        ];
    }
}
