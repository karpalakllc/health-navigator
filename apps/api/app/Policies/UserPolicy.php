<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;
use App\Policies\Support\PrivilegeHierarchy;

class UserPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('staff.view') || $user->can('clients.view');
    }

    public function view(User $user, User $model): bool
    {
        return $model->isStaff()
            ? $user->can('staff.view')
            : $user->can('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('staff.create') || $user->can('clients.create');
    }

    public function update(User $user, User $model): bool
    {
        // A deleted (anonymised) account is an empty shell kept for its public
        // content; giving it an address or password again would revive it.
        if ($model->isAnonymised()) {
            return false;
        }

        $permitted = $model->isStaff()
            ? $user->can('staff.update')
            : $user->can('clients.update');

        return $permitted && PrivilegeHierarchy::canManageUser($user, $model);
    }

    /**
     * Suspending (and lifting it) is for client accounts only: staff are managed
     * through their roles. Never one's own account, never a deleted one, and
     * never an account above the actor in the privilege hierarchy — a community
     * moderator holds more than plain staff with `clients.suspend` might.
     */
    public function suspend(User $user, User $model): bool
    {
        return $model->isClient()
            && ! $model->isAnonymised()
            && ! $user->is($model)
            && $user->can('clients.suspend')
            && PrivilegeHierarchy::canManageUser($user, $model);
    }

    /**
     * Client accounts cannot be deleted here: reviews, forum topics and posts
     * reference users with restrictOnDelete, so a plain delete fails for any
     * client who has contributed, and erasure needs anonymisation instead.
     */
    public function delete(User $user, User $model): bool
    {
        return $model->isStaff()
            && $user->can('staff.delete')
            && PrivilegeHierarchy::canManageUser($user, $model);
    }
}
