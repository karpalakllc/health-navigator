<?php

namespace App\Support;

use App\Http\Resources\Api\V1\HomeSpecialtyResource;
use App\Models\Specialty;
use App\Support\Search\FuzzyNameSearch;
use Illuminate\Support\Facades\Log;
use Throwable;

final class UnifiedSearchCoordinator
{
    public const SPECIALTIES_LIMIT = 8;

    public function __construct(
        private readonly UnifiedSearch $sql,
        private readonly MeilisearchUnifiedSearch $meilisearch,
        private readonly FuzzyNameSearch $fuzzyName,
    ) {}

    /**
     * @return array{
     *     doctors: array{data: mixed, meta: array<string, int>},
     *     facilities: array{data: mixed, meta: array<string, int>},
     *     pharmacies: array{data: mixed, meta: array<string, int>},
     *     products: array{data: mixed, meta: array<string, int>},
     *     forum_topics?: array{data: mixed, meta: array<string, int>},
     *     specialties: list<array<string, mixed>>,
     *     grand_total: int,
     *     fuzzy: bool,
     *     suggestion: string|null
     * }
     */
    public function search(?string $q, ?string $city, int $perPage): array
    {
        $fromMeilisearch = false;
        $verticals = $this->searchVerticals($q, $city, $perPage, $fromMeilisearch);
        $specialties = $this->specialties($q);

        // Nothing matched exactly (either engine): try names that are close —
        // „domenika“ for „Доминика“. The web then says the results are
        // approximate and offers the suggestion („Дали мислевте“).
        if ($verticals['grand_total'] === 0 && $specialties === [] && FuzzyNameSearch::eligible($q)) {
            $fuzzy = $this->sql->fuzzySearch((string) $q, $city, $perPage);
            $fuzzySpecialties = $this->fuzzySpecialties((string) $q);

            if ($fuzzy !== null || $fuzzySpecialties !== []) {
                $specialtyScores = $this->fuzzyName->specialties((string) $q);
                $suggestion = $fuzzy['suggestion'] ?? ($specialtyScores === [] ? null : reset($specialtyScores)[1]);
                unset($fuzzy['suggestion']);

                return [
                    ...($fuzzy ?? $verticals),
                    'specialties' => $fuzzySpecialties,
                    'fuzzy' => true,
                    'suggestion' => $suggestion,
                ];
            }
        }

        // Meilisearch is typo tolerant itself, so its hits may already be
        // approximate: say so when they are, and offer the specialties close
        // to the query as the SQL fallback would.
        $suggestion = null;

        if ($fromMeilisearch && $specialties === [] && FuzzyNameSearch::eligible($q)) {
            $suggestion = $this->approximateSuggestion((string) $q, $verticals);

            if ($suggestion !== null) {
                $specialties = $this->fuzzySpecialties((string) $q);
            }
        }

        return [
            ...$verticals,
            'specialties' => $specialties,
            'fuzzy' => $suggestion !== null,
            'suggestion' => $suggestion,
        ];
    }

    /**
     * For typo-tolerant (Meilisearch) results: the words of the best shown
     * name that are close to the query but not the query itself — „Доминика“
     * for „domenika“, or of a specialty („Кардиологија“ for „kardolog“).
     * Null when a shown name contains the query (in either script), when
     * products or forum topics matched, or when nothing is close enough:
     * hits on a city or a description are not typos.
     *
     * @param  array<string, mixed>  $verticals
     */
    private function approximateSuggestion(string $q, array $verticals): ?string
    {
        if (($verticals['products']['meta']['total'] ?? 0) > 0 || ($verticals['forum_topics']['meta']['total'] ?? 0) > 0) {
            return null;
        }

        $names = [];

        foreach (['doctors' => 'full_name', 'facilities' => 'name', 'pharmacies' => 'name'] as $vertical => $key) {
            foreach ($verticals[$vertical]['data'] ?? [] as $item) {
                if (is_array($item) && is_string($item[$key] ?? null)) {
                    $names[] = $item[$key];
                }
            }
        }

        $name = FuzzyNameSearch::closestNameMatch($q, $names);

        if ($name === false) {
            return null;
        }

        // A doctor hit may come from a misspelt specialty („kardolog“).
        $specialties = $this->fuzzyName->specialties($q);
        $best = $specialties === [] ? $name : reset($specialties);

        if ($name[0] > $best[0]) {
            $best = $name;
        }

        return $best[0] >= FuzzyNameSearch::THRESHOLD && $best[1] !== '' ? $best[1] : null;
    }

    /**
     * Published specialties close to the query (FuzzyNameSearch), best first.
     *
     * @return list<array<string, mixed>>
     */
    private function fuzzySpecialties(string $q): array
    {
        $ids = array_slice(array_keys($this->fuzzyName->specialties($q)), 0, self::SPECIALTIES_LIMIT);

        if ($ids === []) {
            return [];
        }

        $specialties = Specialty::query()
            ->published()
            ->whereIn('id', $ids)
            ->withCount(['doctors as doctors_count' => fn ($query) => $query->published()])
            ->get()
            ->sortBy(fn (Specialty $specialty): int => (int) array_search($specialty->id, $ids, true))
            ->values();

        /** @var list<array<string, mixed>> */
        return HomeSpecialtyResource::collection($specialties)->resolve();
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
    private function searchVerticals(?string $q, ?string $city, int $perPage, bool &$fromMeilisearch): array
    {
        // City-only browse has no relevance to rank: the SQL listing (featured,
        // then name) is the right order and avoids an empty-query index scan.
        $cityOnly = ($q === null || trim($q) === '') && $city !== null && trim($city) !== '';

        if (! $cityOnly && MeilisearchGateway::isHealthy()) {
            try {
                $result = $this->meilisearch->search($q, $city, $perPage);
                $fromMeilisearch = true;

                return $result;
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
