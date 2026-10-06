<?php

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;
use App\Policies\Concerns\ChecksResourcePermissions;

class DoctorPolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'doctors';
    }

    /**
     * „Мој профил“: the member account staff linked to this profile, while it
     * is active. Suspended and deleted accounts are refused at the token
     * already; checked again so no other caller can skip it.
     */
    public function manageOwnProfile(User $user, Doctor $doctor): bool
    {
        return $doctor->isOwnedBy($user)
            && ! $user->isSuspended()
            && ! $user->isAnonymised();
    }

    /** Link or unlink the managing account (admin panel). */
    public function assignOwner(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.assign_owner');
    }
}
