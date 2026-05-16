<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reviews.view');
    }

    public function view(User $user, Review $review): bool
    {
        return $user->can('reviews.view');
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Member;
    }

    public function update(User $user, Review $review): bool
    {
        return $user->can('reviews.update');
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->can('reviews.delete');
    }
}
