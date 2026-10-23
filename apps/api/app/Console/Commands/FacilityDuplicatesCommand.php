<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Import\Facilities\FacilityDuplicatePlanner;
use Illuminate\Console\Command;

/**
 * Website-only facilities that duplicate a register facility
 * (docs/data-import.md §6a). The dry run (default) only reads: it prints
 * counts and can write the plan to a file. --apply merges the
 * high-confidence proposals (FacilityMerger) and queues the others for
 * staff („Merge into register facility…“ in the import review queue).
 */
class FacilityDuplicatesCommand extends Command
{
    protected $signature = 'import:facility-duplicates
        {--apply : Merge the high-confidence proposals and queue the others for review}
        {--review-only : With --apply: queue every proposal, merge nothing}
        {--by= : E-mail of the staff account the merges are attributed to}
        {--json= : Write the plan (every proposal, with reasons, and the counts) to this file}
        {--facility=* : Only these website facility ids}';

    protected $description = 'Find website-only facilities that duplicate a register facility; merge the certain ones (--apply) and queue the rest';

    public function handle(FacilityDuplicatePlanner $planner): int
    {
        $apply = (bool) $this->option('apply');
        $by = null;

        if ($apply) {
            $email = (string) $this->option('by');
            $by = $email !== '' ? User::query()->where('email', $email)->first() : null;

            if ($by === null) {
                $this->error('--apply needs --by=<e-mail of a staff account>.');

                return self::INVALID;
            }
        }

        $only = array_values(array_filter(array_map('intval', (array) $this->option('facility'))));
        $plan = $planner->plan($only === [] ? null : $only);

        if (is_string($this->option('json')) && $this->option('json') !== '') {
            file_put_contents((string) $this->option('json'), json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->line('Plan written to '.$this->option('json'));
        }

        $this->table(['count', 'value'], collect($plan['counts'])->map(fn ($value, $key) => [$key, $value])->values()->all());

        if (! $apply) {
            $this->line('Dry run: nothing written. Run with --apply --by=<e-mail> to merge the high-confidence proposals and queue the others.');

            return self::SUCCESS;
        }

        $result = $planner->apply($plan, $by, mergeHigh: ! $this->option('review-only'));
        $this->table(['result', 'value'], collect($result)->map(fn ($value, $key) => [$key, $value])->values()->all());
        $this->line('Run import:adjudicate and urgent-care:derive next: they re-read the moved staff pages and departments.');

        return self::SUCCESS;
    }
}
