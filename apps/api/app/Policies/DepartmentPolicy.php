<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;

class DepartmentPolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'departments';
    }
}
