<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * User management in Filament is admin-only.
     * Moderators can access the panel but must not create or edit accounts/roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin();
    }
}
