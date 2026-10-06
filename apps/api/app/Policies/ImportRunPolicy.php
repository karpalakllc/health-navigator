<?php

namespace App\Policies;

use App\Models\ImportRun;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

/**
 * Import runs are written by the importers only; staff read them and
 * download their diff summaries (names of people: imports.view).
 */
class ImportRunPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('imports.view');
    }

    public function view(User $user, ImportRun $run): bool
    {
        return $user->can('imports.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ImportRun $run): bool
    {
        return false;
    }

    public function delete(User $user, ImportRun $run): bool
    {
        return false;
    }
}
