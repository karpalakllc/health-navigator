<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
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
}
