<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;

class LanguagePolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'languages';
    }
}
