<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksResourcePermissions
{
    abstract protected static function permissionPrefix(): string;

    public function viewAny(User $user): bool
    {
        return $user->can(static::permissionPrefix().'.view');
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->can(static::permissionPrefix().'.view');
    }

    public function create(User $user): bool
    {
        return $user->can(static::permissionPrefix().'.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->can(static::permissionPrefix().'.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return $user->can(static::permissionPrefix().'.delete');
    }

    public function restore(User $user, mixed $model): bool
    {
        return $user->can(static::permissionPrefix().'.update');
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return $user->can(static::permissionPrefix().'.delete');
    }
}
