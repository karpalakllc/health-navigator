<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;

class ClinicalInterestPolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'clinical_interests';
    }
}
