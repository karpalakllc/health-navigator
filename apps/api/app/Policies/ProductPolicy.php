<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;

class ProductPolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'products';
    }
}
