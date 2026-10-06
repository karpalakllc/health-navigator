<?php

namespace App\Policies;

use App\Models\DoctorChangeRequest;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

/**
 * The admin queue of linked doctors' change requests. Deciding one writes to
 * the doctor profile, so it takes the same right as editing it
 * (doctors.update). Doctors file requests through the API only, and requests
 * are kept as the record of what was decided.
 */
class DoctorChangeRequestPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('doctors.update');
    }

    public function view(User $user, DoctorChangeRequest $request): bool
    {
        return $user->can('doctors.update');
    }

    public function create(User $user): bool
    {
        return false;
    }

    /** Approve or reject. */
    public function update(User $user, DoctorChangeRequest $request): bool
    {
        return $user->can('doctors.update');
    }

    public function delete(User $user, DoctorChangeRequest $request): bool
    {
        return false;
    }
}
