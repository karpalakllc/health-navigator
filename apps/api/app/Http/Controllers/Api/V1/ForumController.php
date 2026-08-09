<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListForumPostsRequest;
use App\Http\Requests\Api\V1\ListForumTopicsRequest;
use App\Http\Requests\Api\V1\StoreForumPostRequest;
use App\Http\Requests\Api\V1\StoreForumTopicRequest;
use App\Http\Requests\Api\V1\UpdateForumTopicModerationRequest;
use App\Http\Resources\Api\V1\ForumCategoryResource;
use App\Http\Resources\Api\V1\ForumPostResource;
use App\Http\Resources\Api\V1\ForumTopicDetailResource;
use App\Http\Resources\Api\V1\ForumTopicListResource;
use App\Http\Resources\Api\V1\ForumTopicSearchResource;
use App\Http\Resources\Api\V1\MyForumPostResource;
use App\Http\Resources\Api\V1\MyForumTopicResource;
use App\Http\Responses\ApiResponse;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Services\AnalyticsService;
use App\Support\Forum\ForumContentModeration;
use App\Support\ForumAuthorCounts;
use App\Support\Slug;
use App\Support\UgcMailer;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ForumController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    public function recentTopics(ListForumTopicsRequest $request): JsonResponse
    {
        $perPage = min($request->validated()['per_page'] ?? 8, 20);

        $paginator = ForumTopic::query()
            ->approved()
            ->with(['user', 'category'])
            ->orderByDesc('last_post_at')
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            ForumTopicSearchResource::collection($paginator),
        );
    }

    public function searchTopics(ListForumTopicsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = $validated['per_page'] ?? 15;
        $q = $validated['q'] ?? null;

        if ($q === null || $q === '') {
            $empty = new LengthAwarePaginator([], 0, $perPage, 1);

            return ApiResponse::paginated(
                $empty,
                ForumTopicSearchResource::collection($empty),
            );
        }

        $paginator = $this->forumTopicSearchPaginator(
            $q,
            $perPage,
            $validated['category'] ?? null,
        );

        return ApiResponse::paginated(
            $paginator,
            ForumTopicSearchResource::collection($paginator),
        );
    }

    public function indexCategories(): JsonResponse
    {
        $categories = ForumCategory::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->withCount(['topics' => fn ($query) => $query->approved()])
            ->get();

        return ApiResponse::success(ForumCategoryResource::collection($categories));
    }

    public function indexTopics(string $category, ListForumTopicsRequest $request): JsonResponse
    {
        $categoryModel = $this->publishedCategory($category);
        $validated = $request->validated();

        $query = ForumTopic::query()
            ->where('forum_category_id', $categoryModel->id)
            ->approved()
            ->with('user');

        if (($validated['sort'] ?? 'latest') === 'active') {
            $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('replies_count')
                ->orderByDesc('last_post_at');
        } else {
            $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('last_post_at')
                ->orderByDesc('published_at');
        }

        if (! empty($validated['q'])) {
            $query->searchTitle($validated['q']);
        }

        $perPage = $validated['per_page'] ?? 15;
        $paginator = $query->paginate($perPage)->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            ForumTopicListResource::collection($paginator),
        );
    }

    public function showTopic(
        string $category,
        string $topic,
        ListForumPostsRequest $request,
    ): JsonResponse {
        $categoryModel = $this->publishedCategory($category);
        $topicModel = $this->approvedTopic($categoryModel, $topic);
        $validated = $request->validated();

        $topicModel->load([
            'user' => ForumAuthorCounts::eagerLoad(),
            'category',
        ]);

        $postsQuery = $topicModel->posts()
            ->approved()
            ->with(['user' => ForumAuthorCounts::eagerLoad()])
            ->orderBy('published_at');

        $perPage = $validated['per_page'] ?? 20;
        $paginator = $postsQuery->paginate($perPage)->withQueryString();

        $related = ForumTopic::query()
            ->where('forum_category_id', $categoryModel->id)
            ->approved()
            ->whereKeyNot($topicModel->id)
            ->with('user')
            ->orderByDesc('last_post_at')
            ->limit(3)
            ->get();

        return response()->json([
            'data' => [
                'topic' => (new ForumTopicDetailResource($topicModel))->resolve($request),
                'posts' => ForumPostResource::collection($paginator)->resolve(),
                'related_topics' => ForumTopicListResource::collection($related)->resolve(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function storeTopic(string $category, StoreForumTopicRequest $request): JsonResponse
    {
        $this->authorize('create', ForumTopic::class);

        $categoryModel = $this->publishedCategory($category);
        $user = $request->user();
        $baseSlug = Slug::fromName($request->string('title')->toString());

        $slug = UniqueSlug::forQuery(
            ForumTopic::query()->where('forum_category_id', $categoryModel->id),
            $baseSlug,
        );

        $status = ForumContentModeration::initialTopicStatus($user);

        $topic = ForumTopic::query()->create([
            'forum_category_id' => $categoryModel->id,
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => $request->string('title')->toString(),
            'body' => $request->string('body')->toString(),
            'status' => $status,
        ]);

        if (ForumContentModeration::shouldNotifyAuthor($user, $status)) {
            UgcMailer::notifySubmitted($topic);
        }

        $this->analytics->record('forum.topic_created', $user, [
            'category_id' => $categoryModel->id,
            'topic_id' => $topic->id,
        ]);

        return ApiResponse::success([
            'slug' => $topic->slug,
            'title' => $topic->title,
            'status' => $topic->status->value,
            'created_at' => $topic->created_at?->toIso8601String(),
        ], 201);
    }

    public function storePost(
        string $category,
        string $topic,
        StoreForumPostRequest $request,
    ): JsonResponse {
        $this->authorize('create', ForumPost::class);

        $categoryModel = $this->publishedCategory($category);
        $topicModel = $this->approvedTopic($categoryModel, $topic);

        if ($topicModel->is_locked) {
            throw ValidationException::withMessages([
                'topic' => [__('api.forum.topic_locked')],
            ]);
        }

        $user = $request->user();
        $status = ForumContentModeration::initialPostStatus($user);

        $post = ForumPost::query()->create([
            'forum_topic_id' => $topicModel->id,
            'user_id' => $user->id,
            'body' => $request->string('body')->toString(),
            'status' => $status,
        ]);

        if (ForumContentModeration::shouldNotifyAuthor($user, $status)) {
            UgcMailer::notifySubmitted($post);
        }

        $this->analytics->record('forum.post_created', $request->user(), [
            'topic_id' => $topicModel->id,
            'post_id' => $post->id,
        ]);

        return ApiResponse::success([
            'id' => $post->id,
            'body' => $post->body,
            'status' => $post->status->value,
            'created_at' => $post->created_at?->toIso8601String(),
        ], 201);
    }

    public function updateTopicModeration(
        string $category,
        string $topic,
        UpdateForumTopicModerationRequest $request,
    ): JsonResponse {
        $categoryModel = $this->publishedCategory($category);
        $topicModel = $this->approvedTopic($categoryModel, $topic);

        $this->authorize('update', $topicModel);

        $updates = [];

        if ($request->has('is_pinned')) {
            $updates['is_pinned'] = $request->boolean('is_pinned');
        }

        if ($request->has('is_locked')) {
            $updates['is_locked'] = $request->boolean('is_locked');
        }

        $topicModel->update($updates);

        return ApiResponse::success([
            'slug' => $topicModel->slug,
            'is_pinned' => $topicModel->is_pinned,
            'is_locked' => $topicModel->is_locked,
        ]);
    }

    public function myTopics(ListForumTopicsRequest $request): JsonResponse
    {
        $perPage = $request->validated()['per_page'] ?? 15;

        $paginator = ForumTopic::query()
            ->where('user_id', $request->user()->id)
            ->with('category')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            MyForumTopicResource::collection($paginator),
        );
    }

    public function myPosts(ListForumPostsRequest $request): JsonResponse
    {
        $perPage = $request->validated()['per_page'] ?? 15;

        $paginator = ForumPost::query()
            ->where('user_id', $request->user()->id)
            ->with(['topic.category'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            MyForumPostResource::collection($paginator),
        );
    }

    /**
     * @return LengthAwarePaginator<ForumTopic>
     */
    private function forumTopicSearchPaginator(
        string $q,
        int $perPage,
        ?string $categorySlug = null,
    ): LengthAwarePaginator {
        $categoryId = null;

        if ($categorySlug !== null && $categorySlug !== '') {
            $categoryId = ForumCategory::query()
                ->published()
                ->where('slug', $categorySlug)
                ->value('id');

            if ($categoryId === null) {
                return new LengthAwarePaginator([], 0, $perPage, 1);
            }
        }

        if (config('scout.driver') === 'meilisearch') {
            return ForumTopic::search($q)
                ->query(function ($builder) use ($categoryId) {
                    $builder->approved()->with(['user', 'category']);

                    if ($categoryId !== null) {
                        $builder->where('forum_category_id', $categoryId);
                    }
                })
                ->paginate($perPage);
        }

        $query = ForumTopic::query()
            ->approved()
            ->with(['user', 'category'])
            ->searchTitle($q);

        if ($categoryId !== null) {
            $query->where('forum_category_id', $categoryId);
        }

        return $query
            ->orderByDesc('last_post_at')
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    private function publishedCategory(string $slug): ForumCategory
    {
        return ForumCategory::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function approvedTopic(ForumCategory $category, string $slug): ForumTopic
    {
        return ForumTopic::query()
            ->where('forum_category_id', $category->id)
            ->approved()
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
