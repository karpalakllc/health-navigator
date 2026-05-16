<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
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
        return $model->isStaff()
            ? $user->can('staff.update')
            : $user->can('clients.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $model->isStaff()
            ? $user->can('staff.delete')
            : false;
    }
}
