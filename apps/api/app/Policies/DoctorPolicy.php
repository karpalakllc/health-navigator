<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;

class DoctorPolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'doctors';
    }
}
