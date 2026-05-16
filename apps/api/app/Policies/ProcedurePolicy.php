<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;

class ProcedurePolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'procedures';
    }
}
