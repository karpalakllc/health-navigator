<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

class ReviewPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('reviews.view');
    }

    public function view(User $user, Review $review): bool
    {
        return $user->can('reviews.view');
    }

    /**
     * Held through the Member role, which registration assigns. Staff do not
     * hold it unless an administrator grants it.
     */
    public function create(User $user): bool
    {
        return $user->can('reviews.create');
    }

    public function update(User $user, Review $review): bool
    {
        return $user->can('reviews.update');
    }

    /**
     * Attach, edit or remove the official response of the reviewed doctor or
     * facility (entered by staff on their behalf).
     */
    public function respond(User $user, Review $review): bool
    {
        return $user->can('reviews.respond');
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->can('reviews.delete');
    }
}
