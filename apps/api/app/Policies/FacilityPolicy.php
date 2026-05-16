<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;

class FacilityPolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'facilities';
    }
}
