<?php

namespace App\Policies;

use App\Models\DoctorClaimRequest;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

/**
 * „Ова е мој профил“ requests. Handling one means assigning an account to a
 * profile (or declining to), so it takes doctors.assign_owner. Members file
 * them through the API only; they are kept as the record of the decision.
 */
class DoctorClaimRequestPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('doctors.assign_owner');
    }

    public function view(User $user, DoctorClaimRequest $request): bool
    {
        return $user->can('doctors.assign_owner');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DoctorClaimRequest $request): bool
    {
        return $user->can('doctors.assign_owner');
    }

    public function delete(User $user, DoctorClaimRequest $request): bool
    {
        return false;
    }
}
