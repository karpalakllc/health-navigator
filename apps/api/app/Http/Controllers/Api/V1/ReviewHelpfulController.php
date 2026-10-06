<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\Review;
use App\Support\ReviewHelpfulVotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * „Корисно“ on a published review: PUT marks it, DELETE takes the mark back.
 * Both are idempotent and answer with the new count, so the client can settle
 * its optimistic state on whatever the server ended up with.
 */
class ReviewHelpfulController extends Controller
{
    public function store(int $review, Request $request): JsonResponse
    {
        $model = $this->publishedReview($review);

        if ((int) $model->user_id === (int) $request->user()->getKey()) {
            throw ValidationException::withMessages([
                'review' => [__('api.review.helpful_own')],
            ]);
        }

        // Nor the doctor whose profile it is: their votes would push the
        // reviews they like up the „most helpful“ order of their own page.
        if ($model->reviewable instanceof Doctor && $model->reviewable->isOwnedBy($request->user())) {
            throw ValidationException::withMessages([
                'review' => [__('api.review.helpful_own_profile')],
            ]);
        }

        return ApiResponse::success(ReviewHelpfulVotes::add($model, $request->user()));
    }

    public function destroy(int $review, Request $request): JsonResponse
    {
        return ApiResponse::success(ReviewHelpfulVotes::remove($this->publishedReview($review), $request->user()));
    }

    private function publishedReview(int $id): Review
    {
        return Review::query()
            ->approved()
            ->whereKey($id)
            ->onPublicProfile()
            ->firstOrFail();
    }
}
