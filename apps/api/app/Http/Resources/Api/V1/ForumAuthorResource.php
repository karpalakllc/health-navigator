<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ForumAuthorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'member_since' => $this->created_at?->toIso8601String(),
            'topics_count' => (int) ($this->forum_topics_count ?? 0),
            'posts_count' => (int) ($this->forum_posts_count ?? 0),
            'is_team_member' => $this->isStaff(),
            'is_forum_moderator' => $this->isForumModerator(),
        ];
    }
}
