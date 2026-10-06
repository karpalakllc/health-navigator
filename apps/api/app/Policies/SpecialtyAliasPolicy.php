<?php

namespace App\Policies;

use App\Models\SpecialtyAlias;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

/**
 * Specialty aliases (a source's specialty wording → our specialty) are
 * created by the importers on first sight. Staff with imports.view read
 * them; imports.manage corrects the mapping. Nobody creates or deletes one
 * by hand: a deleted alias would simply come back with the catalogue
 * default on the next run.
 */
class SpecialtyAliasPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('imports.view');
    }

    public function view(User $user, SpecialtyAlias $alias): bool
    {
        return $user->can('imports.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SpecialtyAlias $alias): bool
    {
        return $user->can('imports.manage');
    }

    public function delete(User $user, SpecialtyAlias $alias): bool
    {
        return false;
    }
}
