<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;

class SpecialtyPolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'specialties';
    }
}
