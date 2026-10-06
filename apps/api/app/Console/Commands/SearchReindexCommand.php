<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTopic;
use App\Support\MeilisearchGateway;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\MeilisearchEngine;
use Throwable;

class SearchReindexCommand extends Command
{
    protected $signature = 'search:reindex {--flush : Remove all records from indexes before importing}';

    protected $description = 'Sync index settings and import doctors, facilities, pharmacies, and forum topics into Meilisearch';

    /** @var list<class-string<Model>> */
    private const MODELS = [Doctor::class, Facility::class, ForumTopic::class];

    public function handle(EngineManager $engines): int
    {
        if (! MeilisearchGateway::isConfigured()) {
            $this->error('SCOUT_DRIVER must be set to meilisearch.');

            return self::FAILURE;
        }

        try {
            if ($this->option('flush')) {
                foreach (self::MODELS as $model) {
                    if ($this->call('scout:flush', ['model' => $model]) !== self::SUCCESS) {
                        return $this->failed("Flushing [{$model}] failed.");
                    }
                }
            }

            // Filterable attributes live in config/scout.php; without syncing them
            // first, every filtered search is rejected by Meilisearch. Not via
            // scout:sync-index-settings: it reports a failure and still exits 0.
            $this->syncIndexSettings($engines->engine('meilisearch'));

            foreach (self::MODELS as $model) {
                if ($this->call('scout:import', ['model' => $model]) !== self::SUCCESS) {
                    return $this->failed("Importing [{$model}] failed.");
                }
            }
        } catch (Throwable $exception) {
            return $this->failed($exception->getMessage());
        }

        MeilisearchGateway::forgetHealthCache();

        $this->info('Search indexes rebuilt.');

        return self::SUCCESS;
    }

    /**
     * The same resolution as scout:sync-index-settings — a model class key
     * goes to its indexableAs(), a plain name gets the Scout prefix — but an
     * engine error propagates instead of being printed and swallowed.
     */
    private function syncIndexSettings(mixed $engine): void
    {
        if (! $engine instanceof MeilisearchEngine) {
            throw new \RuntimeException('The meilisearch engine does not support index settings.');
        }

        foreach ((array) config('scout.meilisearch.index-settings', []) as $name => $settings) {
            $settings = (array) $settings;

            if (class_exists($name)) {
                $model = new $name;
                $index = $model->indexableAs();

                if (config('scout.soft_delete', false) && in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                    $settings = $engine->configureSoftDeleteFilter($settings);
                }
            } else {
                $prefix = (string) config('scout.prefix');
                $index = $prefix !== '' && str_starts_with($name, $prefix) ? $name : $prefix.$name;
            }

            $engine->updateIndexSettings($index, $settings);

            $this->info("Settings for the [{$index}] index synced.");
        }
    }

    private function failed(string $message): int
    {
        $this->error('Search reindex failed: '.$message);

        return self::FAILURE;
    }
}
