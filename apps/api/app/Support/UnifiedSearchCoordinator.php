<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

final class UnifiedSearchCoordinator
{
    public function __construct(
        private readonly UnifiedSearch $sql,
        private readonly MeilisearchUnifiedSearch $meilisearch,
    ) {}

    /**
     * @return array{
     *     doctors: array{data: mixed, meta: array<string, int>},
     *     facilities: array{data: mixed, meta: array<string, int>},
     *     pharmacies: array{data: mixed, meta: array<string, int>},
     *     products: array{data: mixed, meta: array<string, int>},
     *     forum_topics?: array{data: mixed, meta: array<string, int>},
     *     grand_total: int
     * }
     */
    public function search(?string $q, ?string $city, int $perPage): array
    {
        if (MeilisearchGateway::isHealthy()) {
            try {
                return $this->meilisearch->search($q, $city, $perPage);
            } catch (Throwable $exception) {
                MeilisearchGateway::forgetHealthCache();

                Log::warning('Meilisearch unified search failed; falling back to SQL.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $this->sql->search($q, $city, $perPage);
    }
}
