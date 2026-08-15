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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

final class UnifiedSearch
{
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
        if ($q === null || $q === '') {
            return $this->emptyResult();
        }

        $perPage = min(max(1, $perPage), self::MAX_PER_VERTICAL);

        $settings = SiteSetting::current();

        $doctors = $this->searchDoctors($q, $city, $perPage);
        $facilities = $this->searchFacilities($q, $city, $perPage);
        $pharmacies = $settings->public_pharmacies
            ? $this->searchPharmacies($q, $city, $perPage)
            : $this->emptyPaginator();
        $products = $settings->public_products
            ? $this->searchProducts($q, $perPage)
            : $this->emptyPaginator();
        $forumTopics = $settings->public_forum
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
     * @return LengthAwarePaginator<Doctor>
     */
    private function searchDoctors(?string $q, ?string $city, int $perPage): LengthAwarePaginator
    {
        $query = ReviewSummary::eagerLoad(Doctor::query())
            ->published()
            ->with(['specialties' => fn ($relation) => $relation->published()])
            ->orderBy('full_name');

        if ($city !== null && $city !== '') {
            $query->cityContains($city);
        }

        if ($q !== null && $q !== '') {
            $query->searchName($q);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<Facility>
     */
    private function searchFacilities(?string $q, ?string $city, int $perPage): LengthAwarePaginator
    {
        $query = ReviewSummary::eagerLoad(Facility::query())
            ->published()
            ->clinical()
            ->orderBy('name');

        if ($city !== null && $city !== '') {
            $query->cityContains($city);
        }

        if ($q !== null && $q !== '') {
            $query->searchName($q);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<Facility>
     */
    private function searchPharmacies(?string $q, ?string $city, int $perPage): LengthAwarePaginator
    {
        $query = ReviewSummary::eagerLoad(Facility::query())
            ->published()
            ->pharmacy()
            ->orderBy('name');

        if ($city !== null && $city !== '') {
            $query->cityContains($city);
        }

        if ($q !== null && $q !== '') {
            $query->searchName($q);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<ForumTopic>
     */
    private function searchForumTopics(?string $q, int $perPage): LengthAwarePaginator
    {
        $query = ForumTopic::query()
            ->approved()
            ->with(['user', 'category'])
            ->orderByDesc('last_post_at')
            ->orderByDesc('published_at');

        if ($q !== null && $q !== '') {
            $query->searchTitle($q);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<Product>
     */
    private function searchProducts(?string $q, int $perPage): LengthAwarePaginator
    {
        $query = Product::query()
            ->published()
            ->orderBy('name');

        if ($q !== null && $q !== '') {
            $query->searchName($q);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array{
     *     doctors: array{data: array<int, mixed>, meta: array<string, int>},
     *     facilities: array{data: array<int, mixed>, meta: array<string, int>},
     *     pharmacies: array{data: array<int, mixed>, meta: array<string, int>},
     *     products: array{data: array<int, mixed>, meta: array<string, int>},
     *     grand_total: int
     * }
     */
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
