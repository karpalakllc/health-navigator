<?php

namespace App\Support;

use App\Http\Resources\Api\V1\HomeSpecialtyResource;
use App\Models\Specialty;
use Illuminate\Support\Facades\Log;
use Throwable;

final class UnifiedSearchCoordinator
{
    public const SPECIALTIES_LIMIT = 8;

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
     *     specialties: list<array<string, mixed>>,
     *     grand_total: int
     * }
     */
    public function search(?string $q, ?string $city, int $perPage): array
    {
        return [
            ...$this->searchVerticals($q, $city, $perPage),
            'specialties' => $this->specialties($q),
        ];
    }

    /**
     * Published specialties whose name matches the query (either script),
     * with their published-doctor count: shortcuts into the doctor directory
     * filtered by specialty. Not counted in grand_total — the doctors they
     * lead to already are (doctor search matches specialty names too).
     *
     * @return list<array<string, mixed>>
     */
    private function specialties(?string $q): array
    {
        if ($q === null || trim($q) === '') {
            return [];
        }

        $publishedDoctors = fn ($query) => $query->published();

        $specialties = Specialty::query()
            ->published()
            ->searchName(trim($q))
            ->withCount(['doctors as doctors_count' => $publishedDoctors])
            ->orderByDesc('doctors_count')
            ->orderBy('name')
            ->limit(self::SPECIALTIES_LIMIT)
            ->get();

        /** @var list<array<string, mixed>> */
        return HomeSpecialtyResource::collection($specialties)->resolve();
    }

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
    private function searchVerticals(?string $q, ?string $city, int $perPage): array
    {
        if (MeilisearchGateway::isHealthy()) {
            try {
                return $this->meilisearch->search($q, $city, $perPage);
            } catch (Throwable $exception) {
                MeilisearchGateway::forgetHealthCache();

                Log::warning('Meilisearch unified search failed; falling back to SQL.', [
                    'message' => $exception->getMessage(),
                ]);
                // Reported too: a log line alone hides a broken production index
                // behind a silently slower, less relevant SQL search.
                report($exception);
            }
        }

        return $this->sql->search($q, $city, $perPage);
    }
}
