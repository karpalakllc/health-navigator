<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;

class ForumCategoryPolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'forum_categories';
    }
}
