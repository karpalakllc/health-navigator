<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UsernameTerm;

/**
 * The blocked and reserved username lists: one permission, usernames.manage,
 * for every ability. Every ability Filament checks is defined, because a
 * missing method means "allow" to Filament (ResourceAuthorizationCoverageTest).
 */
class UsernameTermPolicy
{
    private function manages(User $user): bool
    {
        return $user->can('usernames.manage');
    }

    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, UsernameTerm $term): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, UsernameTerm $term): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, UsernameTerm $term): bool
    {
        return $this->manages($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function restore(User $user, UsernameTerm $term): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, UsernameTerm $term): bool
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

    public function replicate(User $user, UsernameTerm $term): bool
    {
        return $this->manages($user);
    }

    public function attach(User $user): bool
    {
        return false;
    }

    public function detach(User $user, UsernameTerm $term): bool
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

    public function dissociate(User $user, UsernameTerm $term): bool
    {
        return false;
    }

    public function dissociateAny(User $user): bool
    {
        return false;
    }
}
