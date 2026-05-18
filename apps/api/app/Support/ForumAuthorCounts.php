<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ForumAuthorCounts
{
    /**
     * @return Closure(Relation<User, mixed, *>): void
     */
    public static function eagerLoad(): Closure
    {
        return static function (Relation $relation): void {
            $relation->with('roles')->withCount([
                'forumTopics as forum_topics_count' => static fn ($query) => $query->approved(),
                'forumPosts as forum_posts_count' => static fn ($query) => $query->approved(),
            ]);
        };
    }
}
