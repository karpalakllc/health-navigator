<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTopic;
use App\Support\MeilisearchGateway;
use Illuminate\Console\Command;

class SearchReindexCommand extends Command
{
    protected $signature = 'search:reindex {--flush : Remove all records from indexes before importing}';

    protected $description = 'Sync index settings and import doctors, facilities, pharmacies, and forum topics into Meilisearch';

    public function handle(): int
    {
        if (! MeilisearchGateway::isConfigured()) {
            $this->error('SCOUT_DRIVER must be set to meilisearch.');

            return self::FAILURE;
        }

        if ($this->option('flush')) {
            $this->call('scout:flush', ['model' => Doctor::class]);
            $this->call('scout:flush', ['model' => Facility::class]);
            $this->call('scout:flush', ['model' => ForumTopic::class]);
        }

        // Filterable attributes live in config/scout.php; without syncing them
        // first, every filtered search is rejected by Meilisearch.
        $this->call('scout:sync-index-settings');

        foreach ([Doctor::class, Facility::class, ForumTopic::class] as $model) {
            $this->call('scout:import', ['model' => $model]);
        }

        MeilisearchGateway::forgetHealthCache();

        $this->info('Search indexes rebuilt.');

        return self::SUCCESS;
    }
}
