<?php

namespace App\Policies;

use App\Models\ProfileCorrection;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

/**
 * The public correction and objection queue. Closing a request takes
 * profile_corrections.resolve; fixing the profile itself is done on the
 * profile's own edit page, under its own permission. Requests arrive through
 * the public API only and are kept as the record of the decision.
 */
class ProfileCorrectionPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('profile_corrections.view');
    }

    public function view(User $user, ProfileCorrection $correction): bool
    {
        return $user->can('profile_corrections.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProfileCorrection $correction): bool
    {
        return $user->can('profile_corrections.resolve');
    }

    public function delete(User $user, ProfileCorrection $correction): bool
    {
        return false;
    }
}
