<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListReviewsRequest;
use App\Http\Requests\Api\V1\StoreReviewRequest;
use App\Http\Resources\Api\V1\MyReviewResource;
use App\Http\Resources\Api\V1\PublicReviewResource;
use App\Http\Resources\Api\V1\ViewerReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Services\AnalyticsService;
use App\Support\ReviewHelpfulVotes;
use App\Support\UgcMailer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    public function indexForDoctor(string $slug, ListReviewsRequest $request): JsonResponse
    {
        $doctor = Doctor::query()->published()->where('slug', $slug)->firstOrFail();

        return $this->paginatedReviews($doctor, $request);
    }

    public function indexForFacility(string $slug, ListReviewsRequest $request): JsonResponse
    {
        $facility = Facility::query()
            ->published()
            ->clinical()
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->paginatedReviews($facility, $request);
    }

    public function indexForPharmacy(string $slug, ListReviewsRequest $request): JsonResponse
    {
        $pharmacy = Facility::query()
            ->published()
            ->pharmacy()
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->paginatedReviews($pharmacy, $request);
    }

    public function storeForDoctor(string $slug, StoreReviewRequest $request): JsonResponse
    {
        $doctor = Doctor::query()->published()->where('slug', $slug)->firstOrFail();

        return $this->storeReview($doctor, $request);
    }

    public function storeForFacility(string $slug, StoreReviewRequest $request): JsonResponse
    {
        $facility = Facility::query()
            ->published()
            ->clinical()
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->storeReview($facility, $request);
    }

    public function storeForPharmacy(string $slug, StoreReviewRequest $request): JsonResponse
    {
        $pharmacy = Facility::query()
            ->published()
            ->pharmacy()
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->storeReview($pharmacy, $request);
    }

    public function myReviews(ListReviewsRequest $request): JsonResponse
    {
        $perPage = $request->validated()['per_page'] ?? 15;

        $paginator = Review::query()
            ->where('user_id', $request->user()->id)
            ->with('reviewable')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            MyReviewResource::collection($paginator),
        );
    }

    private function paginatedReviews(Doctor|Facility $reviewable, ListReviewsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = $validated['per_page'] ?? 15;

        $query = $reviewable->reviews()
            ->approved()
            ->with('user');

        if (! empty($validated['rating'])) {
            $query->where('rating', (int) $validated['rating']);
        }

        // Every order ends on id, so equal values cannot swap rows between pages.
        match ($validated['sort'] ?? 'newest') {
            'oldest' => $query->oldest('published_at')->orderBy('id'),
            'rating_high' => $query->orderByDesc('rating')->latest('published_at')->orderByDesc('id'),
            'rating_low' => $query->orderBy('rating')->latest('published_at')->orderByDesc('id'),
            'helpful' => $query->orderByDesc('helpful_count')->latest('published_at')->orderByDesc('id'),
            default => $query->latest('published_at')->orderByDesc('id'),
        };

        $paginator = $query->paginate($perPage)->withQueryString();

        // Official responses are signed with the profile's name; every review
        // here belongs to this profile, so no per-row lookup is needed.
        $paginator->getCollection()->each(fn (Review $review) => $review->setRelation('reviewable', $reviewable));

        // „Корисно“ state for the signed-in viewer only: anonymous lists stay
        // identical for everyone (and so cacheable).
        if ($request->user()) {
            $voted = ReviewHelpfulVotes::votedBy(
                $request->user(),
                $paginator->getCollection()->map(fn (Review $review): int => (int) $review->getKey())->values()->all(),
            );
            $paginator->getCollection()->each(function (Review $review) use ($voted): void {
                $review->viewerHasVotedHelpful = isset($voted[(int) $review->getKey()]);
            });
        }

        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'rating_counts' => $this->ratingCounts($reviewable),
        ];

        if ($request->user()) {
            $viewerReview = $reviewable->reviews()
                ->where('user_id', $request->user()->id)
                ->first();

            if ($viewerReview) {
                $meta['viewer_review'] = ViewerReviewResource::make($viewerReview)->resolve();
            }
        }

        return response()->json([
            'data' => PublicReviewResource::collection($paginator),
            'meta' => $meta,
        ]);
    }

    /**
     * How many approved reviews gave each star, for the profile's histogram.
     * Independent of the list's own rating filter and page, so the bars stay
     * put while the visitor filters. One grouped query.
     *
     * @return array<int, int>
     */
    private function ratingCounts(Doctor|Facility $reviewable): array
    {
        $counts = $reviewable->reviews()
            ->approved()
            ->toBase()
            ->selectRaw('rating, count(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $result = [];

        foreach ([1, 2, 3, 4, 5] as $stars) {
            $result[$stars] = (int) ($counts[$stars] ?? 0);
        }

        return $result;
    }

    private function storeReview(Doctor|Facility $reviewable, StoreReviewRequest $request): JsonResponse
    {
        $this->authorize('create', Review::class);

        $user = $request->user();

        if (
            Review::query()
                ->where('user_id', $user->id)
                ->where('reviewable_type', $reviewable::class)
                ->where('reviewable_id', $reviewable->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'review' => [__('api.review.duplicate')],
            ]);
        }

        try {
            $review = Review::query()->create([
                'user_id' => $user->id,
                'reviewable_type' => $reviewable::class,
                'reviewable_id' => $reviewable->id,
                'rating' => $request->integer('rating'),
                'body' => $request->input('body'),
                'status' => ReviewStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'review' => [__('api.review.duplicate')],
            ]);
        }

        UgcMailer::notifySubmitted($review);

        $this->analytics->record('review.submitted', $user, [
            'reviewable_type' => $reviewable::class,
            'reviewable_id' => $reviewable->id,
            'rating' => $review->rating,
        ]);

        return ApiResponse::success([
            'rating' => $review->rating,
            'body' => $review->body,
            'status' => $review->status->value,
            'created_at' => $review->created_at?->toIso8601String(),
        ], 201);
    }
}
