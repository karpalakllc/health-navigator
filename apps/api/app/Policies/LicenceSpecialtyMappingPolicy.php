<?php

namespace App\Policies;

use App\Models\LicenceSpecialtyMapping;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

/**
 * The licence specialty mapping: licences.manage for every ability staff
 * use. Rows are not deleted — a wording that is not a physician's specialty
 * is marked ignored, so the next import does not add it back as unmapped.
 */
class LicenceSpecialtyMappingPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('licences.manage');
    }

    public function view(User $user, LicenceSpecialtyMapping $mapping): bool
    {
        return $user->can('licences.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('licences.manage');
    }

    public function update(User $user, LicenceSpecialtyMapping $mapping): bool
    {
        return $user->can('licences.manage');
    }

    public function delete(User $user, LicenceSpecialtyMapping $mapping): bool
    {
        return false;
    }
}
