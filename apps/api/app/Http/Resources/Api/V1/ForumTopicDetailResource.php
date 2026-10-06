<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ForumTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ForumTopic
 */
class ForumTopicDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body,
            'author_name' => $this->user->publicName(),
            'author' => new ForumAuthorResource($this->user),
            'category' => [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ],
            'replies_count' => $this->replies_count,
            'is_locked' => $this->is_locked,
            'is_pinned' => $this->is_pinned,
            'published_at' => $this->published_at?->toIso8601String(),
            // Signed-in requests only (anonymous payloads carry no viewer
            // state): whether this is the viewer's own topic (no „Пријави“),
            // and can_moderate only when it is true.
            'viewer' => $this->when(
                $request->user() !== null,
                fn (): array => [
                    'is_own' => (int) $this->user_id === (int) $request->user()?->getKey(),
                    ...($request->user()?->canModerateForumTopic($this->resource) ? ['can_moderate' => true] : []),
                ],
            ),
        ];
    }
}
