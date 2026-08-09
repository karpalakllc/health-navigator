<?php

namespace App\Support\Forum;

use App\Enums\ForumContentStatus;
use App\Models\SiteSetting;
use App\Models\User;

final class ForumContentModeration
{
    public static function initialTopicStatus(User $user): ForumContentStatus
    {
        return self::resolve($user, SiteSetting::current()->forum_topics_require_moderation);
    }

    public static function initialPostStatus(User $user): ForumContentStatus
    {
        return self::resolve($user, SiteSetting::current()->forum_posts_require_moderation);
    }

    public static function shouldNotifyAuthor(User $user, ForumContentStatus $status): bool
    {
        return $status === ForumContentStatus::Pending;
    }

    private static function resolve(User $user, bool $requiresModeration): ForumContentStatus
    {
        if ($user->can('forum.moderate')) {
            return ForumContentStatus::Approved;
        }

        if (! $requiresModeration) {
            return ForumContentStatus::Approved;
        }

        return ForumContentStatus::Pending;
    }
}
