<?php

namespace App\Console\Commands;

use App\Support\UrgentCare\UrgentCareDeriver;
use Illuminate\Console\Command;

/**
 * Re-reads the imported wording (ФЗОМ work units, website departments and
 * hours) and switches on urgent-care flags with strong evidence
 * (docs/urgent-care.md § Data). The imports run the same thing at their end.
 */
class DeriveUrgentCareCommand extends Command
{
    protected $signature = 'urgent-care:derive {--dry-run : Report what would change without writing}';

    protected $description = 'Set urgent-care flags (emergency department, emergency medical service, dental emergency, 24/7) from imported source wording';

    public function handle(UrgentCareDeriver $deriver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $summary = $deriver->run(null, ! $dryRun);

        $this->table(
            ['Facility', 'City', 'Flags '.($dryRun ? 'that would be set' : 'set')],
            array_map(fn (array $row): array => [$row['name'], (string) $row['city'], implode(', ', $row['flags'])], $summary['set']),
        );

        $this->info(sprintf(
            '%s: %d flag(s) on %d facilit(y/ies); %d facilities with evidence, %d candidate item(s) for staff, %d kept off by staff, %d skipped (confirmed by staff).',
            $dryRun ? 'Dry run' : 'Done',
            $summary['flags_set'],
            count($summary['set']),
            $summary['facilities_with_evidence'],
            $summary['candidates'],
            $summary['kept_off_by_staff'],
            $summary['confirmed_by_staff'],
        ));

        return self::SUCCESS;
    }
}
