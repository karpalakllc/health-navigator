<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ForumTopic;
use App\Models\User;

class ForumTopicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, ForumTopic $forumTopic): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Member;
    }

    public function update(User $user, ForumTopic $forumTopic): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, ForumTopic $forumTopic): bool
    {
        return false;
    }
}
