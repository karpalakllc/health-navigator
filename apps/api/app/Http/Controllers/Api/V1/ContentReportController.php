<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreContentReportRequest;
use App\Http\Responses\ApiResponse;
use App\Models\ContentReport;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Member reports of published content (docs/notice-and-action.md). Only what
 * the public can see can be reported, so a hidden or pending item (or a
 * pharmacy review while that module is off) answers 404 exactly as its public
 * page does. A member's own content answers 422. Reporting the same item twice is not an
 * error: the first report stands and the answer is the same, so a retry or a
 * double click cannot fill the queue.
 */
class ContentReportController extends Controller
{
    public function storeForReview(int $review, StoreContentReportRequest $request): JsonResponse
    {
        $model = Review::query()
            ->approved()
            ->whereKey($review)
            ->onPublicProfile()
            ->firstOrFail();

        return $this->store($model, $request);
    }

    public function storeForForumTopic(string $category, string $topic, StoreContentReportRequest $request): JsonResponse
    {
        return $this->store($this->visibleTopic($category, $topic), $request);
    }

    public function storeForForumPost(int $post, StoreContentReportRequest $request): JsonResponse
    {
        $model = ForumPost::query()
            ->approved()
            ->whereKey($post)
            ->whereHas('topic', fn (Builder $query) => $query->visible())
            ->firstOrFail();

        return $this->store($model, $request);
    }

    private function store(Model $reportable, StoreContentReportRequest $request): JsonResponse
    {
        // Authors take their own content down by asking, not by reporting it.
        if ((int) $reportable->getAttribute('user_id') === (int) $request->user()->getKey()) {
            throw ValidationException::withMessages([
                'content' => [__('api.report.own_content')],
            ]);
        }

        $attributes = [
            'user_id' => $request->user()->getKey(),
            'reportable_type' => $reportable::class,
            'reportable_id' => $reportable->getKey(),
        ];

        try {
            $report = ContentReport::query()->firstOrCreate($attributes, [
                'reason' => $request->reason(),
                'note' => $request->note(),
            ]);
            $created = $report->wasRecentlyCreated;
        } catch (UniqueConstraintViolationException) {
            // A concurrent duplicate won the insert; same outcome as a repeat.
            $created = false;
        }

        return ApiResponse::success([
            'status' => 'received',
            'message' => __('api.report.received'),
        ], $created ? 201 : 200);
    }

    private function visibleTopic(string $category, string $topic): ForumTopic
    {
        $categoryModel = ForumCategory::query()
            ->published()
            ->where('slug', $category)
            ->firstOrFail();

        return ForumTopic::query()
            ->where('forum_category_id', $categoryModel->id)
            ->approved()
            ->where('slug', $topic)
            ->firstOrFail();
    }
}
