<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Review $review): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Member;
    }

    public function update(User $user, Review $review): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Review $review): bool
    {
        return false;
    }
}
