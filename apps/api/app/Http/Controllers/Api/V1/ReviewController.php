<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewAspect;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListReviewsRequest;
use App\Http\Requests\Api\V1\StoreReviewRequest;
use App\Http\Resources\Api\V1\MyReviewResource;
use App\Http\Resources\Api\V1\PublicReviewResource;
use App\Http\Resources\Api\V1\RemovedContentResource;
use App\Http\Resources\Api\V1\ViewerReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Services\AnalyticsService;
use App\Support\ReviewHelpfulVotes;
use App\Support\ReviewInsights;
use App\Support\UgcMailer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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

        // Published reviews plus the placeholder of every removed one, so a
        // removal is never silent. A star filter is about the published
        // ratings, so it leaves the placeholders out.
        $query = $reviewable->reviews()
            ->inPublicList()
            ->with(['user', 'aspectRatings']);

        if (! empty($validated['rating'])) {
            $query->approved()->where('rating', (int) $validated['rating']);
        }

        // A placeholder sorts by date where the review was (removed_at only
        // for placeholders backfilled without a publication date) and after
        // the published reviews in rating and helpfulness orders, which must
        // not reveal its rating or votes. Every order ends on id, so equal
        // values cannot swap rows between pages.
        $date = 'coalesce(published_at, removed_at)';
        $removedLast = 'case when removed_at is null then 0 else 1 end';

        match ($validated['sort'] ?? 'newest') {
            'oldest' => $query->orderByRaw("{$date} asc")->orderBy('id'),
            'rating_high' => $query->orderByRaw($removedLast)->orderByDesc('rating')->orderByRaw("{$date} desc")->orderByDesc('id'),
            'rating_low' => $query->orderByRaw($removedLast)->orderBy('rating')->orderByRaw("{$date} desc")->orderByDesc('id'),
            'helpful' => $query->orderByRaw($removedLast)->orderByDesc('helpful_count')->orderByRaw("{$date} desc")->orderByDesc('id'),
            default => $query->orderByRaw("{$date} desc")->orderByDesc('id'),
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
            // Per-aspect averages (withheld below three ratings) and the
            // twelve-month trend (null below five reviews); approved only.
            'aspects' => ReviewInsights::aspects($reviewable),
            'trend' => ReviewInsights::trend($reviewable),
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
            'data' => $paginator->getCollection()
                ->map(fn (Review $review): array => $review->isRemoved()
                    ? RemovedContentResource::make($review)->resolve($request)
                    : PublicReviewResource::make($review)->resolve($request))
                ->values(),
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

        // The doctor managing the profile cannot rate it („Мој профил“).
        if ($reviewable instanceof Doctor && $reviewable->isOwnedBy($user)) {
            throw ValidationException::withMessages([
                'review' => [__('api.review.own_profile')],
            ]);
        }

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

        $aspects = $this->validatedAspects($reviewable, $request);

        try {
            $review = DB::transaction(function () use ($user, $reviewable, $request, $aspects): Review {
                $review = Review::query()->create([
                    'user_id' => $user->id,
                    'reviewable_type' => $reviewable::class,
                    'reviewable_id' => $reviewable->id,
                    'rating' => $request->integer('rating'),
                    'body' => $request->input('body'),
                    'status' => ReviewStatus::Pending,
                ]);

                foreach ($aspects as $aspect => $rating) {
                    $review->aspectRatings()->create(['aspect' => $aspect, 'rating' => $rating]);
                }

                return $review;
            });
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
            'aspects' => (object) $aspects,
            'status' => $review->status->value,
            'created_at' => $review->created_at?->toIso8601String(),
        ], 201);
    }

    /**
     * The sub-ratings the member gave, keyed by aspect code. Unrated aspects
     * (null) are dropped; a code the profile's type does not have is refused.
     *
     * @return array<string, int>
     */
    private function validatedAspects(Doctor|Facility $reviewable, StoreReviewRequest $request): array
    {
        $given = $request->validated('aspects') ?? [];
        $allowed = ReviewAspect::valuesFor($reviewable::class);
        $aspects = [];

        foreach ($given as $aspect => $rating) {
            if (! in_array((string) $aspect, $allowed, true)) {
                throw ValidationException::withMessages([
                    'aspects' => [__('api.review.aspect_unknown')],
                ]);
            }

            if ($rating !== null) {
                $aspects[(string) $aspect] = (int) $rating;
            }
        }

        return $aspects;
    }
}
