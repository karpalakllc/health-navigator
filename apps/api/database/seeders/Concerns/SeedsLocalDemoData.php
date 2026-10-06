<?php

namespace Database\Seeders\Concerns;

use App\Support\DeploymentEnvironment;

trait SeedsLocalDemoData
{
    /**
     * Demo/directory seeders run outside deployments (DeploymentEnvironment),
     * or when SEED_LOCAL_DEMO=true.
     */
    protected function shouldRunLocalDemoSeeders(): bool
    {
        if (! DeploymentEnvironment::isDeployed()) {
            return true;
        }

        return (bool) config('zdravje.seed.local_demo');
    }
}
