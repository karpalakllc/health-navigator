<?php

namespace App\Support;

use App\Http\Resources\Api\V1\DoctorListResource;
use App\Http\Resources\Api\V1\FacilityListResource;
use App\Http\Resources\Api\V1\ForumTopicSearchResource;
use App\Http\Resources\Api\V1\PharmacyListResource;
use App\Http\Resources\Api\V1\ProductListResource;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTopic;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\Search\FuzzyMatch;
use App\Support\Search\FuzzyNameSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

final class UnifiedSearch
{
    public function __construct(
        private readonly FuzzyNameSearch $fuzzy,
    ) {}

    public const DEFAULT_PER_VERTICAL = 5;

    public const MAX_PER_VERTICAL = 10;

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
        $q = $q === '' ? null : $q;
        $city = $city !== null && trim($city) !== '' ? trim($city) : null;

        if ($q === null && $city === null) {
            return $this->emptyResult();
        }

        // City-only browse: everything that has a city, ordered by the
        // directory defaults (featured, then name). Products and forum topics
        // have no city, so they are skipped rather than dumped unfiltered.
        $cityOnly = $q === null;

        $perPage = min(max(1, $perPage), self::MAX_PER_VERTICAL);

        $settings = SiteSetting::current();

        $doctors = $this->searchDoctors($q, $city, $perPage);
        $facilities = $this->searchFacilities($q, $city, $perPage);
        $pharmacies = $settings->public_pharmacies
            ? $this->searchPharmacies($q, $city, $perPage)
            : $this->emptyPaginator();
        $products = $settings->public_products && ! $cityOnly
            ? $this->searchProducts($q, $perPage)
            : $this->emptyPaginator();
        $forumTopics = $settings->public_forum && ! $cityOnly
            ? $this->searchForumTopics($q, $perPage)
            : $this->emptyPaginator();

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
     * The exact search found nothing: the same verticals, matched by name
     * similarity instead (FuzzyNameSearch), with the same published scopes,
     * city filter and page size. Products and forum topics have no fuzzy
     * match and stay empty. Null when nothing is close either.
     *
     * @return array{
     *     doctors: array{data: mixed, meta: array<string, int>},
     *     facilities: array{data: mixed, meta: array<string, int>},
     *     pharmacies: array{data: mixed, meta: array<string, int>},
     *     products: array{data: mixed, meta: array<string, int>},
     *     forum_topics: array{data: mixed, meta: array<string, int>},
     *     grand_total: int,
     *     suggestion: string|null
     * }|null
     */
    public function fuzzySearch(string $q, ?string $city, int $perPage): ?array
    {
        $q = trim($q);
        $city = $city !== null && trim($city) !== '' ? trim($city) : null;

        if (! FuzzyNameSearch::eligible($q)) {
            return null;
        }

        $perPage = min(max(1, $perPage), self::MAX_PER_VERTICAL);

        $doctorMatch = $this->fuzzy->doctors($this->doctorsQuery($city), $q);
        $facilityMatch = $this->fuzzy->facilities($this->facilitiesQuery($city), $q);
        $pharmacyMatch = SiteSetting::current()->public_pharmacies
            ? $this->fuzzy->facilities($this->pharmaciesQuery($city), $q)
            : null;

        if ($doctorMatch === null && $facilityMatch === null && $pharmacyMatch === null) {
            return null;
        }

        // One correction across the page: every directory is cut at the
        // overall best match minus the window, not only at its own best.
        $cutoff = max($doctorMatch->bestScore ?? 0.0, $facilityMatch->bestScore ?? 0.0, $pharmacyMatch->bestScore ?? 0.0)
            - FuzzyNameSearch::WINDOW;
        $doctorMatch = $doctorMatch?->above($cutoff);
        $facilityMatch = $facilityMatch?->above($cutoff);
        $pharmacyMatch = $pharmacyMatch?->above($cutoff);

        $doctors = $doctorMatch !== null
            ? $this->orderDoctors(FuzzyNameSearch::applyToDoctors($this->doctorsQuery($city), $doctorMatch))->paginate($perPage)
            : $this->emptyPaginator($perPage);
        $facilities = $facilityMatch !== null
            ? $this->orderFacilities(FuzzyNameSearch::applyToFacilities($this->facilitiesQuery($city), $facilityMatch))->paginate($perPage)
            : $this->emptyPaginator($perPage);
        $pharmacies = $pharmacyMatch !== null
            ? $this->orderFacilities(FuzzyNameSearch::applyToFacilities($this->pharmaciesQuery($city), $pharmacyMatch))->paginate($perPage)
            : $this->emptyPaginator($perPage);
        $empty = $this->emptyPaginator($perPage);

        return [
            'doctors' => $this->section($doctors, DoctorListResource::class),
            'facilities' => $this->section($facilities, FacilityListResource::class),
            'pharmacies' => $this->section($pharmacies, PharmacyListResource::class),
            'products' => $this->section($empty, ProductListResource::class),
            'forum_topics' => $this->section($empty, ForumTopicSearchResource::class),
            'grand_total' => $doctors->total() + $facilities->total() + $pharmacies->total(),
            'suggestion' => FuzzyMatch::bestSuggestion($doctorMatch, $facilityMatch, $pharmacyMatch),
        ];
    }

    /**
     * @return LengthAwarePaginator<Doctor>
     */
    private function searchDoctors(?string $q, ?string $city, int $perPage): LengthAwarePaginator
    {
        $query = $this->doctorsQuery($city);

        if ($q !== null && $q !== '') {
            $query->searchNameOrSpecialty($q);
        }

        return $this->orderDoctors($query)->paginate($perPage);
    }

    /**
     * Published doctors in the city, with what DoctorListResource renders.
     *
     * @return Builder<Doctor>
     */
    private function doctorsQuery(?string $city): Builder
    {
        $query = Doctor::query()
            ->published()
            ->with([
                'specialties' => fn ($relation) => $relation->published(),
                // DoctorListResource reads the primary facility; without this it
                // lazy-loads once per doctor, unpublished facilities included.
                'facilities' => fn ($relation) => $relation->where('facilities.is_published', true),
            ]);

        if ($city !== null && $city !== '') {
            $query->cityContains($city);
        }

        return $query;
    }

    /**
     * Featured first, as in the directory lists (DoctorController).
     *
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    private function orderDoctors(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderBy('full_name');
    }

    /**
     * @return LengthAwarePaginator<Facility>
     */
    private function searchFacilities(?string $q, ?string $city, int $perPage): LengthAwarePaginator
    {
        $query = $this->facilitiesQuery($city);

        if ($q !== null && $q !== '') {
            $query->searchNameOrDepartment($q);
        }

        return $this->orderFacilities($query)->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<Facility>
     */
    private function searchPharmacies(?string $q, ?string $city, int $perPage): LengthAwarePaginator
    {
        $query = $this->pharmaciesQuery($city);

        if ($q !== null && $q !== '') {
            $query->searchName($q);
        }

        return $this->orderFacilities($query)->paginate($perPage);
    }

    /**
     * @return Builder<Facility>
     */
    private function facilitiesQuery(?string $city): Builder
    {
        $query = Facility::query()->published()->clinical();

        if ($city !== null && $city !== '') {
            $query->cityContains($city);
        }

        return $query;
    }

    /**
     * @return Builder<Facility>
     */
    private function pharmaciesQuery(?string $city): Builder
    {
        $query = Facility::query()->published()->pharmacy();

        if ($city !== null && $city !== '') {
            $query->cityContains($city);
        }

        return $query;
    }

    /**
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    private function orderFacilities(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderBy('name');
    }

    /**
     * @return LengthAwarePaginator<ForumTopic>
     */
    private function searchForumTopics(?string $q, int $perPage): LengthAwarePaginator
    {
        $query = ForumTopic::query()
            ->visible()
            ->with(['user', 'category'])
            ->orderByDesc('last_post_at')
            ->orderByDesc('published_at');

        if ($q !== null && $q !== '') {
            $query->searchTitle($q);
        }

        return $query->paginate($perPage);
    }

    /**
     * Public because products have no Meilisearch index: the Meilisearch path
     * reuses this query rather than returning an always-empty vertical.
     *
     * @return LengthAwarePaginator<Product>
     */
    public function searchProducts(?string $q, int $perPage): LengthAwarePaginator
    {
        $query = Product::query()
            ->published()
            ->orderBy('name');

        // ProductListResource renders from_price / offer_count; without the
        // aggregates every search hit reported "no offers".
        PharmacyCatalog::withFromPrice($query);
        PharmacyCatalog::withOfferCount($query);

        if ($q !== null && $q !== '') {
            $query->searchName($q);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<Model>
     */
    private function emptyPaginator(int $perPage = self::DEFAULT_PER_VERTICAL): LengthAwarePaginator
    {
        return new Paginator([], 0, $perPage, 1);
    }

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
