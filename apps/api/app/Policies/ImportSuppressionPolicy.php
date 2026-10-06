<?php

namespace App\Policies;

use App\Models\ImportSuppression;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

/**
 * Suppressed profiles are recorded by the system (an upheld objection, a
 * deleted doctor). imports.view reads the list; imports.manage lifts one.
 * Nobody creates or deletes a row by hand.
 */
class ImportSuppressionPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('imports.view');
    }

    public function view(User $user, ImportSuppression $suppression): bool
    {
        return $user->can('imports.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ImportSuppression $suppression): bool
    {
        return $user->can('imports.manage');
    }

    public function delete(User $user, ImportSuppression $suppression): bool
    {
        return false;
    }
}
