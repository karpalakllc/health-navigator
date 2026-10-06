<?php

namespace App\Policies;

use App\Models\ForumTopic;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;
use App\Policies\Support\UsernameChoice;
use Illuminate\Auth\Access\Response;

class ForumTopicPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('forum_topics.view');
    }

    public function view(User $user, ForumTopic $forumTopic): bool
    {
        if (! $user->can('forum_topics.view')) {
            return false;
        }

        if ($user->hasScopedForumModeration()) {
            return $user->canModerateForumCategory($forumTopic->category);
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

    /**
     * The single definition of "may moderate this topic". User::canModerateForumTopic()
     * (which drives `viewer.can_moderate` on the API) delegates here, so the UI
     * flag and the endpoint cannot drift apart.
     */
    public function update(User $user, ForumTopic $forumTopic): bool
    {
        $category = $forumTopic->category;

        if ($category === null) {
            return false;
        }

        // A moderator assigned to specific categories must not act outside them.
        // The old fallback to `forum_topics.update` defeated the scoping entirely,
        // because the Forum Moderator role grants exactly that permission.
        if ($user->hasScopedForumModeration()) {
            return $user->canModerateForumCategory($category);
        }

        return ($user->can('forum.moderate') && $user->canModerateForumCategory($category))
            || $user->can('forum_topics.update');
    }

    public function delete(User $user, ForumTopic $forumTopic): bool
    {
        return false;
    }
}
