<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ForumPost;
use App\Models\User;

class ForumPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('forum_posts.view');
    }

    public function view(User $user, ForumPost $forumPost): bool
    {
        if (! $user->can('forum_posts.view')) {
            return false;
        }

        $category = $forumPost->topic?->category;

        if ($category && $user->hasScopedForumModeration()) {
            return $user->canModerateForumCategory($category);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Member;
    }

    public function update(User $user, ForumPost $forumPost): bool
    {
        $category = $forumPost->topic?->category;

        if ($category && $user->can('forum.moderate') && $user->canModerateForumCategory($category)) {
            return true;
        }

        return $user->can('forum_posts.update');
    }

    public function delete(User $user, ForumPost $forumPost): bool
    {
        return false;
    }
}
