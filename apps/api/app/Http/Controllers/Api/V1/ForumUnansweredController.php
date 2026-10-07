<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListUnansweredForumTopicsRequest;
use App\Http\Resources\Api\V1\ForumTopicSearchResource;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Support\TaxonomyCache;
use Illuminate\Http\JsonResponse;

/**
 * GET /forum/topics/unanswered — „Прашања без одговор“ / „Помогни некому“.
 *
 * Publicly visible topics that nobody but their own author has answered yet
 * (a published reply from anyone else takes a topic off the list; a reply
 * that is later taken down puts it back). Pinned topics are staff notices and
 * locked ones cannot be answered, so both are left out. Newest first: a fresh
 * question is the likeliest to still need an answer and its author the
 * likeliest to still be reading; older ones are a page further on. There is
 * no view counter to rank by, and none is kept for this.
 *
 * Identical for everyone, so it is cached server side (busted by topic saves
 * and reply counter changes, see ForumTopic) and marked public for 60 s.
 */
class ForumUnansweredController extends Controller
{
    public const DEFAULT_PER_PAGE = 10;

    /**
     * Short: a member renaming themselves saves no topic, so an author's old
     * public name lives at most this long (as on /home/highlights).
     */
    public const CACHE_TTL_SECONDS = 300;

    public function __invoke(ListUnansweredForumTopicsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $categorySlug = $validated['category'] ?? null;
        $q = $validated['q'] ?? null;
        $minAgeHours = (int) ($validated['min_age_hours'] ?? 0);
        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);
        $page = (int) ($validated['page'] ?? 1);

        $categoryId = null;

        if ($categorySlug !== null && $categorySlug !== '') {
            $categoryId = ForumCategory::query()
                ->published()
                ->where('slug', $categorySlug)
                ->value('id');

            abort_if($categoryId === null, 404);
        }

        $resolve = fn (): array => $this->payload($categoryId, $q, $minAgeHours, $perPage, $page);

        // Text searches are too varied to be worth caching.
        $payload = $q === null || $q === ''
            ? TaxonomyCache::remember(
                TaxonomyCache::FORUM_UNANSWERED,
                implode(':', [$categoryId ?? 'all', $minAgeHours, $perPage, $page]),
                $resolve,
                self::CACHE_TTL_SECONDS,
            )
            : $resolve();

        return response()->json($payload);
    }

    /**
     * @return array{data: array<int, mixed>, meta: array<string, int>}
     */
    private function payload(?int $categoryId, ?string $q, int $minAgeHours, int $perPage, int $page): array
    {
        $query = ForumTopic::query()
            ->visible()
            ->unanswered()
            ->where('is_pinned', false)
            ->where('is_locked', false)
            ->with(['user', 'category']);

        if ($categoryId !== null) {
            $query->where('forum_category_id', $categoryId);
        }

        if ($minAgeHours > 0) {
            $query->where('published_at', '<=', now()->subHours($minAgeHours));
        }

        if ($q !== null && $q !== '') {
            $query->searchTitleOrTags($q);
        }

        $paginator = $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => ForumTopicSearchResource::collection($paginator)->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
