<?php

namespace App\Policies;

use App\Models\KomoraLicence;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

/**
 * The Комора licence staging list is read-only in the admin panel: rows come
 * from the import only (licences.manage to look at them).
 */
class KomoraLicencePolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('licences.manage');
    }

    public function view(User $user, KomoraLicence $licence): bool
    {
        return $user->can('licences.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, KomoraLicence $licence): bool
    {
        return false;
    }

    public function delete(User $user, KomoraLicence $licence): bool
    {
        return false;
    }
}
