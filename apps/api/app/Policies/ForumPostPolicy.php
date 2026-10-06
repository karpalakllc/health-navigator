<?php

namespace App\Policies;

use App\Models\ForumPost;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;
use App\Policies\Support\UsernameChoice;
use Illuminate\Auth\Access\Response;

class ForumPostPolicy
{
    use DeniesUndefinedFilamentAbilities;

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

    /**
     * Held through the Member role, which registration assigns. Staff do not
     * hold it unless an administrator grants it. A member who still has a
     * temporary username chooses one first (UsernameChoice).
     */
    public function create(User $user): Response|bool
    {
        if (! $user->can('forum.post')) {
            return false;
        }

        return UsernameChoice::gate($user);
    }

    public function update(User $user, ForumPost $forumPost): bool
    {
        $category = $forumPost->topic?->category;

        // See ForumTopicPolicy::update — scoped moderators must stay in scope.
        if ($user->hasScopedForumModeration()) {
            return $category !== null && $user->canModerateForumCategory($category);
        }

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
