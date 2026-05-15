<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListDoctorsRequest;
use App\Http\Resources\Api\V1\DoctorDetailResource;
use App\Http\Resources\Api\V1\DoctorListResource;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;

class DoctorController extends Controller
{
    public function index(ListDoctorsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Doctor::query()
            ->published()
            ->with(['specialties' => fn ($relation) => $relation->published()])
            ->orderBy('full_name');

        if (! empty($validated['specialty'])) {
            $query->forSpecialtySlug($validated['specialty']);
        }

        if (! empty($validated['city'])) {
            $query->cityContains($validated['city']);
        }

        if (! empty($validated['q'])) {
            $query->searchName($validated['q']);
        }

        if (! empty($validated['featured'])) {
            $query->featured();
        }

        $perPage = $validated['per_page'] ?? 15;

        $paginator = $query->paginate($perPage)->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            DoctorListResource::collection($paginator),
        );
    }

    public function show(string $slug): JsonResponse
    {
        $doctor = Doctor::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'specialties' => fn ($relation) => $relation->published(),
                'facilities' => fn ($relation) => $relation->published()->clinical(),
            ])
            ->firstOrFail();

        return ApiResponse::success(new DoctorDetailResource($doctor));
    }
}
