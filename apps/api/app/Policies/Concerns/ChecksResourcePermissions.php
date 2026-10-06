<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Maps every ability Filament checks onto the resource's view/create/update/delete
 * permissions.
 *
 * Every method here has to exist: when a registered policy lacks the method for
 * an ability, Filament (non-strict mode) *allows* the action — see
 * `Filament\get_authorization_response()`. Before the `*Any` methods were added,
 * a view-only Moderator could bulk delete, force-delete and restore directory
 * records from any list page.
 */
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

    public function deleteAny(User $user): bool
    {
        return $user->can(static::permissionPrefix().'.delete');
    }

    public function restore(User $user, mixed $model): bool
    {
        return $user->can(static::permissionPrefix().'.update');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can(static::permissionPrefix().'.update');
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return $user->can(static::permissionPrefix().'.delete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can(static::permissionPrefix().'.delete');
    }

    public function reorder(User $user): bool
    {
        return $user->can(static::permissionPrefix().'.update');
    }

    public function replicate(User $user, mixed $model): bool
    {
        return $user->can(static::permissionPrefix().'.create');
    }

    // Relationship abilities, consulted when this model is the *related* side of a
    // relation manager. Filament 5's attach/detach actions currently gate on the
    // owner's edit page alone, but defining them keeps any future check from
    // falling through to allow.

    public function attach(User $user): bool
    {
        return $user->can(static::permissionPrefix().'.update');
    }

    public function detach(User $user, mixed $model): bool
    {
        return $user->can(static::permissionPrefix().'.update');
    }

    public function detachAny(User $user): bool
    {
        return $user->can(static::permissionPrefix().'.update');
    }

    public function associate(User $user): bool
    {
        return $user->can(static::permissionPrefix().'.update');
    }

    public function dissociate(User $user, mixed $model): bool
    {
        return $user->can(static::permissionPrefix().'.update');
    }

    public function dissociateAny(User $user): bool
    {
        return $user->can(static::permissionPrefix().'.update');
    }
}
