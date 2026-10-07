<?php

namespace Database\Seeders;

use App\Services\Triage\V2\FlowImporter;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Loads every guidance flow file (database/data/triage/flows) through the v2
 * importer: new or changed files become draft versions awaiting clinician
 * review; only grandfathered flows publish on their first import. Idempotent.
 */
class TriageFlowSeeder extends Seeder
{
    public function run(FlowImporter $importer): void
    {
        $result = $importer->import();

        if (! $result['global']->ok()) {
            throw new RuntimeException('Guidance global screen fails the linter: '.implode(' | ', $result['global']->errors));
        }

        foreach ($result['files'] as $file) {
            if ($file['result'] === 'rejected') {
                // A broken content file must not stop the rest of the seed; it
                // simply stays out. `php artisan triage:lint` shows why.
                $this->command?->warn('Guidance flow rejected by the linter: '.basename($file['path']));
            }
        }
    }
}
