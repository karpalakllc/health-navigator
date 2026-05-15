<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Directory, catalog, and guidance configuration: moderators may view; only admins may mutate.
 */
trait AdminManagesDirectoryRecords
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }
}
