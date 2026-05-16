<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ForumContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListForumPostsRequest;
use App\Http\Requests\Api\V1\ListForumTopicsRequest;
use App\Http\Requests\Api\V1\StoreForumPostRequest;
use App\Http\Requests\Api\V1\StoreForumTopicRequest;
use App\Http\Resources\Api\V1\ForumCategoryResource;
use App\Http\Resources\Api\V1\ForumPostResource;
use App\Http\Resources\Api\V1\ForumTopicDetailResource;
use App\Http\Resources\Api\V1\ForumTopicListResource;
use App\Http\Resources\Api\V1\MyForumPostResource;
use App\Http\Resources\Api\V1\MyForumTopicResource;
use App\Http\Responses\ApiResponse;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Support\ForumAuthorCounts;
use App\Support\Slug;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ForumController extends Controller
{
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
            ->with('user')
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_post_at')
            ->orderByDesc('published_at');

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

        return response()->json([
            'data' => [
                'topic' => (new ForumTopicDetailResource($topicModel))->resolve($request),
                'posts' => ForumPostResource::collection($paginator)->resolve(),
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

        $topic = ForumTopic::query()->create([
            'forum_category_id' => $categoryModel->id,
            'user_id' => $user->id,
            'slug' => $slug,
            'title' => $request->string('title')->toString(),
            'body' => $request->string('body')->toString(),
            'status' => ForumContentStatus::Pending,
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
                'topic' => ['This topic is locked and does not accept new replies.'],
            ]);
        }

        $post = ForumPost::query()->create([
            'forum_topic_id' => $topicModel->id,
            'user_id' => $request->user()->id,
            'body' => $request->string('body')->toString(),
            'status' => ForumContentStatus::Pending,
        ]);

        return ApiResponse::success([
            'id' => $post->id,
            'body' => $post->body,
            'status' => $post->status->value,
            'created_at' => $post->created_at?->toIso8601String(),
        ], 201);
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
