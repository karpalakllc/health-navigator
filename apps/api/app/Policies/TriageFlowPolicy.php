<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksResourcePermissions;

class TriageFlowPolicy
{
    use ChecksResourcePermissions;

    protected static function permissionPrefix(): string
    {
        return 'triage_flows';
    }
}
