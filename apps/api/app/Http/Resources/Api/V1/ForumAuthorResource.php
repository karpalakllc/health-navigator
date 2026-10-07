<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Support\Levels\ContributorLevels;
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
        // A deleted account's posts must not be linkable to each other through
        // a join date or running totals; only the "deleted user" label remains.
        if ($this->isAnonymised()) {
            return [
                'name' => $this->publicName(),
                'member_since' => null,
                'topics_count' => 0,
                'posts_count' => 0,
                'is_team_member' => false,
                'is_forum_moderator' => false,
                'level' => null,
            ];
        }

        return [
            'name' => $this->publicName(),
            'member_since' => $this->created_at?->toIso8601String(),
            'topics_count' => (int) ($this->forum_topics_count ?? 0),
            'posts_count' => (int) ($this->forum_posts_count ?? 0),
            'is_team_member' => $this->isStaff(),
            'is_forum_moderator' => $this->isForumModerator(),
            // W8-C: the forum level („Помошник“…), or null.
            'level' => ContributorLevels::publicLevel($this->resource, 'forum'),
        ];
    }
}
