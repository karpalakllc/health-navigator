<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListDoctorsRequest;
use App\Http\Resources\Api\V1\DoctorDetailResource;
use App\Http\Resources\Api\V1\DoctorListResource;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Support\ReviewSummary;
use Illuminate\Http\JsonResponse;

class DoctorController extends Controller
{
    public function index(ListDoctorsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = ReviewSummary::eagerLoad(Doctor::query())
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
            $query->searchName($validated['q']);
        }

        if (! empty($validated['featured'])) {
            $query->featured();
        }

        $approved = ReviewStatus::Approved->value;

        if (isset($validated['min_reviews']) && $validated['min_reviews'] > 0) {
            $min = (int) $validated['min_reviews'];
            $query->whereRaw(
                '(select count(*) from reviews where reviews.reviewable_id = doctors.id and reviews.reviewable_type = ? and reviews.status = ?) >= ?',
                [Doctor::class, $approved, $min],
            );
        }

        if (($validated['sort'] ?? 'name') === 'rating') {
            $query->orderByRaw(
                '(select avg(rating) from reviews where reviews.reviewable_id = doctors.id and reviews.reviewable_type = ? and reviews.status = ?) is null',
                [Doctor::class, $approved],
            );
            $query->orderByRaw(
                '(select avg(rating) from reviews where reviews.reviewable_id = doctors.id and reviews.reviewable_type = ? and reviews.status = ?) desc',
                [Doctor::class, $approved],
            );
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
        $doctor = ReviewSummary::eagerLoad(Doctor::query())
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
