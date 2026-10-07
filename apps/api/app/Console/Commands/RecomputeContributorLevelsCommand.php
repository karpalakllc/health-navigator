<?php

namespace App\Console\Commands;

use App\Support\Levels\ContributorLevels;
use Illuminate\Console\Command;

/**
 * W8-C: rebuild every member's contributor levels from their public content
 * and clear the cached top lists. Levels are also recomputed on each
 * moderation or vote event; this nightly pass heals anything an event missed
 * (bulk updates without model events, rule changes in LevelRules).
 */
class RecomputeContributorLevelsCommand extends Command
{
    protected $signature = 'levels:recompute';

    protected $description = 'Recompute contributor levels for every member who has published content';

    public function handle(): int
    {
        $count = ContributorLevels::recomputeAll();

        $this->info("Recomputed contributor levels for {$count} member(s).");

        return self::SUCCESS;
    }
}
