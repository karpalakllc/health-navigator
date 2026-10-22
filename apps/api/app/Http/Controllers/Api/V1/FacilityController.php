<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListFacilitiesRequest;
use App\Http\Resources\Api\V1\FacilityDetailResource;
use App\Http\Resources\Api\V1\FacilityListResource;
use App\Http\Responses\ApiResponse;
use App\Models\Facility;
use App\Support\Search\FuzzyNameSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class FacilityController extends Controller
{
    public function index(ListFacilitiesRequest $request, FuzzyNameSearch $fuzzy): JsonResponse
    {
        $validated = $request->validated();

        $query = Facility::query()
            ->published()
            ->clinical();

        if (! empty($validated['type'])) {
            $query->ofType($validated['type']);
        }

        if (! empty($validated['city'])) {
            $query->cityContains($validated['city']);
        }

        if (array_key_exists('has_emergency', $validated) && $validated['has_emergency'] !== null) {
            $query->where('has_emergency_services', (bool) $validated['has_emergency']);
        }

        if (! empty($validated['department'])) {
            $query->whereHas(
                'departments',
                fn ($relation) => $relation->published()->where('slug', $validated['department']),
            );
        }

        if (! empty($validated['featured'])) {
            $query->where('is_featured', true);
        }

        if (! empty($validated['verified'])) {
            $query->verified();
        }

        $q = $validated['q'] ?? null;
        $perPage = $validated['per_page'] ?? 15;
        // Every filter but the name: the base a fuzzy fallback starts from.
        $base = $query->clone();

        if (! empty($q)) {
            $query->searchNameOrDepartment($q);
        }

        $paginator = $this->listed($query)->paginate($perPage)->withQueryString();
        $meta = [];

        // No exact match: names close to the query, under the same filters.
        if (! empty($q) && $paginator->total() === 0 && FuzzyNameSearch::eligible($q)) {
            $match = $fuzzy->facilities($base, $q);
            $meta = ['fuzzy' => $match !== null, 'suggestion' => $match?->suggestion];

            if ($match !== null) {
                $paginator = $this->listed(FuzzyNameSearch::applyToFacilities($base, $match))
                    ->paginate($perPage)
                    ->withQueryString();
            }
        }

        return ApiResponse::paginated(
            $paginator,
            FacilityListResource::collection($paginator),
            $meta,
        );
    }

    /**
     * Featured first within the filters, then by name; the id breaks ties
     * between equal names so pages never overlap or skip rows. (A fuzzy
     * match orders by closeness first; these break its ties.)
     *
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    private function listed(Builder $query): Builder
    {
        return $query
            ->withCount(['departments' => fn ($relation) => $relation->published()])
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->orderBy('facilities.id');
    }

    public function show(string $slug): JsonResponse
    {
        $facility = Facility::query()
            ->published()
            ->clinical()
            ->where('slug', $slug)
            ->with([
                'doctors' => fn ($relation) => $relation->published(),
                'departments' => fn ($relation) => $relation->published(),
            ])
            ->firstOrFail();

        return ApiResponse::success(new FacilityDetailResource($facility));
    }
}
