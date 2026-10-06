<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreContentReportRequest;
use App\Http\Responses\ApiResponse;
use App\Models\ContentReport;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;

/**
 * Member reports of published content (docs/notice-and-action.md). Only what
 * the public can see can be reported, so a hidden or pending item answers 404
 * exactly as its public page does. Reporting the same item twice is not an
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
            ->whereHasMorph('reviewable', [Doctor::class, Facility::class], fn (Builder $query) => $query->where('is_published', true))
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
