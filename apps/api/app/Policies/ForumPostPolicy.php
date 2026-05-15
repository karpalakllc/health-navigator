<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ForumPost;
use App\Models\User;

class ForumPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, ForumPost $forumPost): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Member;
    }

    public function update(User $user, ForumPost $forumPost): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, ForumPost $forumPost): bool
    {
        return false;
    }
}
