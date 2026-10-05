<?php

namespace Database\Seeders\Concerns;

trait SeedsLocalDemoData
{
    /**
     * Demo/directory seeders run on local/testing, or when SEED_LOCAL_DEMO=true.
     */
    protected function shouldRunLocalDemoSeeders(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        if (app()->environment(['local', 'development'])) {
            return true;
        }

        return (bool) config('zdravje.seed.local_demo');
    }
}
