<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListDoctorsRequest;
use App\Http\Resources\Api\V1\DoctorDetailResource;
use App\Http\Resources\Api\V1\DoctorListResource;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Support\Search\FuzzyNameSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class DoctorController extends Controller
{
    public function index(ListDoctorsRequest $request, FuzzyNameSearch $fuzzy): JsonResponse
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

        if (! empty($validated['language'])) {
            $query->speaksLanguage($validated['language']);
        }

        if (! empty($validated['city'])) {
            $query->cityContains($validated['city']);
        }

        if (! empty($validated['featured'])) {
            $query->featured();
        }

        if (! empty($validated['verified'])) {
            $query->verified();
        }

        if (isset($validated['min_reviews']) && $validated['min_reviews'] > 0) {
            $query->where('doctors.reviews_count', '>=', (int) $validated['min_reviews']);
        }

        $q = $validated['q'] ?? null;
        $perPage = $validated['per_page'] ?? 15;
        $sort = $validated['sort'] ?? 'name';
        // Every filter but the name: the base a fuzzy fallback starts from.
        $base = $query->clone();

        if (! empty($q)) {
            $query->searchNameOrSpecialty($q);
        }

        $paginator = $this->order($query, $sort)->paginate($perPage)->withQueryString();
        $meta = [];

        // No exact match: names close to the query, under the same filters.
        if (! empty($q) && $paginator->total() === 0 && FuzzyNameSearch::eligible($q)) {
            $match = $fuzzy->doctors($base, $q);
            $meta = ['fuzzy' => $match !== null, 'suggestion' => $match?->suggestion];

            if ($match !== null) {
                $paginator = $this->order(FuzzyNameSearch::applyToDoctors($base, $match), $sort)
                    ->paginate($perPage)
                    ->withQueryString();
            }
        }

        return ApiResponse::paginated(
            $paginator,
            DoctorListResource::collection($paginator),
            $meta,
        );
    }

    /**
     * Featured profiles lead whatever the visitor filtered to; the chosen
     * sort then orders each group. A plain boolean key, so it is the
     * leading column of an index-backed sort, and totals are unaffected.
     * (A fuzzy match orders by closeness first; these break its ties.)
     *
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    private function order(Builder $query, string $sort): Builder
    {
        $query->orderByDesc('doctors.is_featured');

        if ($sort === 'rating') {
            // Unrated doctors hold rating_avg = 0, below any real average (1–5),
            // so they sort last without an IS NULL key.
            $query->orderByDesc('doctors.rating_avg');
        }

        // Names repeat; the id keeps pages from overlapping or skipping rows.
        return $query->orderBy('doctors.full_name')->orderBy('doctors.id');
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
