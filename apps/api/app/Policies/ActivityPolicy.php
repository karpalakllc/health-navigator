<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Policies\Concerns\DeniesUndefinedFilamentAbilities;

/**
 * The audit log is read-only for everyone: entries are written by the code
 * and removed only by the retention prune (activitylog:clean).
 */
class ActivityPolicy
{
    use DeniesUndefinedFilamentAbilities;

    public function viewAny(User $user): bool
    {
        return $user->can('audit.view');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('audit.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }
}
