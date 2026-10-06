<?php

namespace App\Support;

use App\Enums\FacilityType;
use App\Http\Resources\Api\V1\DoctorListResource;
use App\Http\Resources\Api\V1\FacilityListResource;
use App\Http\Resources\Api\V1\ForumTopicSearchResource;
use App\Http\Resources\Api\V1\PharmacyListResource;
use App\Http\Resources\Api\V1\ProductListResource;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

final class MeilisearchUnifiedSearch
{
    public const DEFAULT_PER_VERTICAL = UnifiedSearch::DEFAULT_PER_VERTICAL;

    public const MAX_PER_VERTICAL = UnifiedSearch::MAX_PER_VERTICAL;

    public function __construct(
        private readonly UnifiedSearch $sql,
    ) {}

    /**
     * @return array{
     *     doctors: array{data: mixed, meta: array<string, int>},
     *     facilities: array{data: mixed, meta: array<string, int>},
     *     pharmacies: array{data: mixed, meta: array<string, int>},
     *     products: array{data: mixed, meta: array<string, int>},
     *     forum_topics: array{data: mixed, meta: array<string, int>},
     *     grand_total: int
     * }
     */
    public function search(?string $q, ?string $city, int $perPage): array
    {
        if ($q === null || $q === '') {
            return $this->emptyResult();
        }

        $perPage = min(max(1, $perPage), self::MAX_PER_VERTICAL);
        $settings = SiteSetting::current();
        $city = $city !== null && trim($city) !== '' ? trim($city) : null;

        $doctors = $this->searchDoctors($q, $city, $perPage);
        $facilities = $this->searchFacilities($q, $city, $perPage, pharmacies: false);
        $pharmacies = $settings->public_pharmacies
            ? $this->searchFacilities($q, $city, $perPage, pharmacies: true)
            : $this->emptyPaginator($perPage);
        // Products have no Meilisearch index; the SQL query is their only source.
        $products = $settings->public_products
            ? $this->sql->searchProducts($q, $perPage)
            : $this->emptyPaginator($perPage);
        $forumTopics = $settings->public_forum
            ? $this->searchForumTopics($q, $perPage)
            : $this->emptyPaginator($perPage);

        return [
            'doctors' => $this->section($doctors, DoctorListResource::class),
            'facilities' => $this->section($facilities, FacilityListResource::class),
            'pharmacies' => $this->section($pharmacies, PharmacyListResource::class),
            'products' => $this->section($products, ProductListResource::class),
            'forum_topics' => $this->section($forumTopics, ForumTopicSearchResource::class),
            'grand_total' => $doctors->total()
                + $facilities->total()
                + $pharmacies->total()
                + $products->total()
                + $forumTopics->total(),
        ];
    }

    /**
     * Every constraint here is a Meilisearch filter. A Scout ->query() callback
     * runs only after Meilisearch has paginated, so filtering there leaves pages
     * short of per_page — it is used only as a guard that re-checks visibility
     * in SQL, for index entries left stale by queued syncs or query-builder
     * updates that bypass the model observers. Scout recounts the total through
     * the same callback, so totals and last_page match what is shown; every
     * search here asks for ids only (MeilisearchGateway::idsOnly()) so that
     * recount does not pull whole documents.
     * Relations and review aggregates are loaded onto the page afterwards.
     *
     * @return LengthAwarePaginator<Doctor>
     */
    private function searchDoctors(string $q, ?string $city, int $perPage): LengthAwarePaginator
    {
        $search = MeilisearchGateway::idsOnly(Doctor::search($q))
            ->query(fn (Builder $query) => $query->published())
            // A sortable attribute (config/scout.php): ranks after relevance,
            // so among equally good matches featured profiles come first.
            ->orderBy('is_featured', 'desc');

        if ($city !== null) {
            $cities = $this->matchingCities(Doctor::query()->published(), $city);

            if ($cities === []) {
                return $this->emptyPaginator($perPage);
            }

            $search->whereIn('city', $cities);
        }

        $paginator = $search->paginate($perPage);

        // review_summary reads the denormalised columns Scout already hydrated.
        $paginator->getCollection()->load([
            'specialties' => fn ($relation) => $relation->published(),
            'facilities' => fn ($relation) => $relation->where('facilities.is_published', true),
        ]);

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<Facility>
     */
    private function searchFacilities(string $q, ?string $city, int $perPage, bool $pharmacies): LengthAwarePaginator
    {
        $search = MeilisearchGateway::idsOnly(Facility::search($q))
            ->query(fn (Builder $query) => $query->published())
            ->orderBy('is_featured', 'desc');

        if ($pharmacies) {
            $search->where('type', FacilityType::Pharmacy->value);
        } else {
            $search->whereIn('type', FacilityType::clinicalValues());
        }

        if ($city !== null) {
            $cities = $this->matchingCities(Facility::query()->published(), $city);

            if ($cities === []) {
                return $this->emptyPaginator($perPage);
            }

            $search->whereIn('city', $cities);
        }

        return $search->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<ForumTopic>
     */
    private function searchForumTopics(string $q, int $perPage): LengthAwarePaginator
    {
        $paginator = MeilisearchGateway::idsOnly(ForumTopic::search($q))
            ->where('category_is_published', true)
            ->query(fn (Builder $query) => $query->visible())
            ->paginate($perPage);

        $paginator->getCollection()->load(['user', 'category']);

        return $paginator;
    }

    /**
     * The SQL path's city filter is a script-insensitive substring match, which
     * a Meilisearch filter cannot express. Resolving it to the exact stored
     * values first keeps both paths matching the same rows. Scout interpolates
     * values into the filter string verbatim, so quotes are escaped; the filter
     * parser unescapes only \" and keeps any other backslash literally, so a
     * value containing one cannot be expressed exactly and is left out.
     *
     * @param  Builder<Doctor>|Builder<Facility>  $query
     * @return list<string>
     */
    private function matchingCities(Builder $query, string $city): array
    {
        return $query
            ->cityContains($city)
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city')
            ->reject(fn (string $value): bool => str_contains($value, '\\'))
            ->map(fn (string $value): string => str_replace('"', '\\"', $value))
            ->values()
            ->all();
    }

    /**
     * @return LengthAwarePaginator<Model>
     */
    private function emptyPaginator(int $perPage = self::DEFAULT_PER_VERTICAL): LengthAwarePaginator
    {
        return new Paginator([], 0, $perPage, 1);
    }

    /**
     * @return array{
     *     doctors: array{data: array<int, mixed>, meta: array<string, int>},
     *     facilities: array{data: array<int, mixed>, meta: array<string, int>},
     *     pharmacies: array{data: array<int, mixed>, meta: array<string, int>},
     *     products: array{data: array<int, mixed>, meta: array<string, int>},
     *     forum_topics: array{data: array<int, mixed>, meta: array<string, int>},
     *     grand_total: int
     * }
     */
    private function emptyResult(): array
    {
        $emptyMeta = [
            'current_page' => 1,
            'per_page' => self::DEFAULT_PER_VERTICAL,
            'total' => 0,
            'last_page' => 1,
        ];

        return [
            'doctors' => ['data' => [], 'meta' => $emptyMeta],
            'facilities' => ['data' => [], 'meta' => $emptyMeta],
            'pharmacies' => ['data' => [], 'meta' => $emptyMeta],
            'products' => ['data' => [], 'meta' => $emptyMeta],
            'forum_topics' => ['data' => [], 'meta' => $emptyMeta],
            'grand_total' => 0,
        ];
    }

    /**
     * @param  LengthAwarePaginator<Model>  $paginator
     * @param  class-string  $resourceClass
     * @return array{data: mixed, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    private function section(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        return [
            'data' => $resourceClass::collection($paginator)->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
