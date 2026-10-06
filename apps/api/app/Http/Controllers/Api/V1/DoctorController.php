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
            ->with([
                'specialties' => fn ($relation) => $relation->published(),
                'facilities' => fn ($relation) => $relation->where('facilities.is_published', true),
            ]);

        if (! empty($validated['specialty'])) {
            $query->forSpecialtySlug($validated['specialty']);
        }

        if (! empty($validated['city'])) {
            $query->cityContains($validated['city']);
        }

        if (! empty($validated['q'])) {
            $query->searchNameOrSpecialty($validated['q']);
        }

        if (! empty($validated['featured'])) {
            $query->featured();
        }

        if (isset($validated['min_reviews']) && $validated['min_reviews'] > 0) {
            $query->where('doctors.reviews_count', '>=', (int) $validated['min_reviews']);
        }

        if (($validated['sort'] ?? 'name') === 'rating') {
            // Unrated doctors hold rating_avg = 0, below any real average (1–5),
            // so they sort last without an IS NULL key.
            $query->orderByDesc('doctors.rating_avg');
            $query->orderBy('doctors.full_name');
        } else {
            $query->orderBy('full_name');
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
                'languages' => fn ($relation) => $relation->published(),
                'clinicalInterests' => fn ($relation) => $relation->published(),
                'procedures' => fn ($relation) => $relation->published(),
            ])
            ->firstOrFail();

        return ApiResponse::success(new DoctorDetailResource($doctor));
    }
}
