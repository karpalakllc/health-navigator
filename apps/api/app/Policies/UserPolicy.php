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
        $permitted = $model->isStaff()
            ? $user->can('staff.update')
            : $user->can('clients.update');

        return $permitted && PrivilegeHierarchy::canManageUser($user, $model);
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
