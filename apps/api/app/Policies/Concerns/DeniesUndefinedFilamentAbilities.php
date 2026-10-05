<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Explicit denials for every Filament ability a hand-written policy does not
 * otherwise implement.
 *
 * Filament (non-strict mode) treats a missing policy method as *allow* — see
 * `Filament\get_authorization_response()`. None of these abilities is offered
 * by the resources using this trait today, so denying them costs nothing and
 * means adding, say, a DeleteBulkAction later fails closed instead of open. A
 * policy that needs one of these overrides it with its own method.
 */
trait DeniesUndefinedFilamentAbilities
{
    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, mixed $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, mixed $model): bool
    {
        return false;
    }

    public function attach(User $user): bool
    {
        return false;
    }

    public function detach(User $user, mixed $model): bool
    {
        return false;
    }

    public function detachAny(User $user): bool
    {
        return false;
    }

    public function associate(User $user): bool
    {
        return false;
    }

    public function dissociate(User $user, mixed $model): bool
    {
        return false;
    }

    public function dissociateAny(User $user): bool
    {
        return false;
    }
}
