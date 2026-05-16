<?php

namespace App\Support;

use App\Http\Resources\Api\V1\DoctorListResource;
use App\Http\Resources\Api\V1\FacilityListResource;
use App\Http\Resources\Api\V1\ForumTopicSearchResource;
use App\Http\Resources\Api\V1\ProductListResource;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

final class MeilisearchUnifiedSearch
{
    public const DEFAULT_PER_VERTICAL = UnifiedSearch::DEFAULT_PER_VERTICAL;

    public const MAX_PER_VERTICAL = UnifiedSearch::MAX_PER_VERTICAL;

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
        $city = $city !== null && $city !== '' ? trim($city) : null;

        $doctors = $this->searchDoctors($q, $city, $perPage);
        $facilities = $this->searchFacilities($q, $city, $perPage);
        $pharmacies = $settings->public_pharmacies
            ? $this->emptyPaginator($perPage)
            : $this->emptyPaginator($perPage);
        $products = $settings->public_products
            ? $this->emptyPaginator($perPage)
            : $this->emptyPaginator($perPage);
        $forumTopics = $settings->public_forum
            ? $this->searchForumTopics($q, $perPage)
            : $this->emptyPaginator($perPage);

        return [
            'doctors' => $this->section($doctors, DoctorListResource::class),
            'facilities' => $this->section($facilities, FacilityListResource::class),
            'pharmacies' => $this->section($pharmacies, FacilityListResource::class),
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
    private function searchDoctors(string $q, ?string $city, int $perPage): LengthAwarePaginator
    {
        return Doctor::search($q)
            ->query(function ($builder) use ($city): void {
                $builder->published()
                    ->with(['specialties' => fn ($relation) => $relation->published()]);

                if ($city !== null) {
                    $builder->cityContains($city);
                }
            })
            ->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<Facility>
     */
    private function searchFacilities(string $q, ?string $city, int $perPage): LengthAwarePaginator
    {
        return Facility::search($q)
            ->query(function ($builder) use ($city): void {
                $builder->published()->clinical();

                if ($city !== null) {
                    $builder->cityContains($city);
                }
            })
            ->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<ForumTopic>
     */
    private function searchForumTopics(string $q, int $perPage): LengthAwarePaginator
    {
        return ForumTopic::search($q)
            ->query(fn ($builder) => $builder->approved()->with(['user', 'category']))
            ->paginate($perPage);
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
