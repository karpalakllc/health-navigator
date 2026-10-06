<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListForumTagsRequest;
use App\Http\Requests\Api\V1\ListForumTopicsRequest;
use App\Http\Requests\Api\V1\RelatedForumTopicsRequest;
use App\Http\Resources\Api\V1\ForumTagResource;
use App\Http\Resources\Api\V1\ForumTopicSearchResource;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTag;
use App\Support\Forum\RelatedForumTopics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Forum keywords: tag pages, the tag list for the sitemap and llms.txt, and
 * forum topics related to a doctor or facility profile (docs/seo.md).
 */
class ForumTagController extends Controller
{
    /**
     * Tags with at least `min_topics` visible topics, most used first.
     */
    public function index(ListForumTagsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $minTopics = (int) ($validated['min_topics'] ?? 1);

        $paginator = ForumTag::query()
            ->withVisibleTopicStats()
            ->whereHas('topics', fn (Builder $topics) => $topics->visible(), '>=', max(1, $minTopics))
            ->orderByDesc('visible_topics_count')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 50)
            ->withQueryString();

        return ApiResponse::paginated($paginator, ForumTagResource::collection($paginator));
    }

    public function show(string $tag, ListForumTopicsRequest $request): JsonResponse
    {
        $tagModel = ForumTag::query()
            ->withVisibleTopicStats()
            ->where('slug', $tag)
            ->firstOrFail();

        // A tag that only pending or hidden topics carry has no public page.
        abort_if((int) $tagModel->getAttribute('visible_topics_count') === 0, 404);

        $paginator = $tagModel->topics()
            ->visible()
            ->with(['user', 'category'])
            ->reorder()
            ->orderByDesc('last_post_at')
            ->orderByDesc('forum_topics.id')
            ->paginate($request->validated()['per_page'] ?? 15)
            ->withQueryString();

        return response()->json([
            'data' => [
                'tag' => (new ForumTagResource($tagModel))->resolve($request),
                'topics' => ForumTopicSearchResource::collection($paginator)->resolve(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /forum/topics/related?doctor={slug} or ?facility={slug}.
     */
    public function related(RelatedForumTopicsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $limit = (int) ($validated['limit'] ?? RelatedForumTopics::DEFAULT_LIMIT);

        if (isset($validated['doctor'])) {
            $doctor = Doctor::query()->published()->where('slug', $validated['doctor'])->firstOrFail();
            $topics = RelatedForumTopics::forDoctor($doctor, $limit);
        } else {
            $facility = Facility::query()->published()->clinical()->where('slug', $validated['facility'])->firstOrFail();
            $topics = RelatedForumTopics::forFacility($facility, $limit);
        }

        return ApiResponse::success(
            ForumTopicSearchResource::collection($topics)->resolve($request),
        );
    }
}
