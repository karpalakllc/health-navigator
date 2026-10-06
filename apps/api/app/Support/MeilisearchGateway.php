<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Builder as ScoutBuilder;
use Meilisearch\Client;
use Throwable;

final class MeilisearchGateway
{
    public static function isConfigured(): bool
    {
        return config('scout.driver') === 'meilisearch';
    }

    public static function isHealthy(): bool
    {
        if (! self::isConfigured()) {
            return false;
        }

        return Cache::remember('meilisearch.health', 30, function (): bool {
            try {
                $client = new Client(
                    config('scout.meilisearch.host'),
                    config('scout.meilisearch.key'),
                );

                $health = $client->health();

                return ($health['status'] ?? null) === 'available';
            } catch (Throwable) {
                return false;
            }
        });
    }

    public static function forgetHealthCache(): void
    {
        Cache::forget('meilisearch.health');
    }

    /**
     * Ask Meilisearch for the primary key of each hit and nothing else.
     *
     * Scout's map() needs only the key (and "_" metadata such as a ranking
     * score, which is not an attribute) to load the page from the database.
     * It matters most for a search with a ->query() guard: Scout then recounts
     * the total with a second search of up to maxTotalHits hits (1000 by
     * default) — with full documents, forum topic bodies included, unless
     * restricted here. Scout adds the key to attributesToRetrieve itself; it is
     * listed anyway so the request stays correct if that ever changes.
     *
     * @template TBuilder of ScoutBuilder
     *
     * @param  TBuilder  $search
     * @return TBuilder
     */
    public static function idsOnly(ScoutBuilder $search): ScoutBuilder
    {
        return $search->options([
            'attributesToRetrieve' => [$search->model->getScoutKeyName()],
        ]);
    }
}
