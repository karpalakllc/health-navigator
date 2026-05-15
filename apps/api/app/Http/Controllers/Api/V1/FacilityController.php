<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListFacilitiesRequest;
use App\Http\Resources\Api\V1\FacilityDetailResource;
use App\Http\Resources\Api\V1\FacilityListResource;
use App\Http\Responses\ApiResponse;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;

class FacilityController extends Controller
{
    public function index(ListFacilitiesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Facility::query()
            ->published()
            ->clinical()
            ->orderBy('name');

        if (! empty($validated['type'])) {
            $query->ofType($validated['type']);
        }

        if (! empty($validated['city'])) {
            $query->cityContains($validated['city']);
        }

        if (! empty($validated['q'])) {
            $query->searchName($validated['q']);
        }

        $perPage = $validated['per_page'] ?? 15;

        $paginator = $query->paginate($perPage)->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            FacilityListResource::collection($paginator),
        );
    }

    public function show(string $slug): JsonResponse
    {
        $facility = Facility::query()
            ->published()
            ->clinical()
            ->where('slug', $slug)
            ->with(['doctors' => fn ($relation) => $relation->published()])
            ->firstOrFail();

        return ApiResponse::success(new FacilityDetailResource($facility));
    }
}
