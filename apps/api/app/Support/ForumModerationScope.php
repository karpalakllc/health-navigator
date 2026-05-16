<?php

namespace App\Support;

use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ForumModerationScope
{
    /**
     * @param  Builder<ForumTopic>  $query
     * @return Builder<ForumTopic>
     */
    public static function restrictTopics(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if ($user === null || ! $user->hasScopedForumModeration()) {
            return $query;
        }

        return $query->whereIn(
            'forum_category_id',
            $user->moderatedForumCategories()->pluck('forum_categories.id'),
        );
    }

    /**
     * @param  Builder<ForumPost>  $query
     * @return Builder<ForumPost>
     */
    public static function restrictPosts(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if ($user === null || ! $user->hasScopedForumModeration()) {
            return $query;
        }

        return $query->whereHas(
            'topic',
            fn (Builder $topicQuery) => self::restrictTopics($topicQuery, $user),
        );
    }
}
