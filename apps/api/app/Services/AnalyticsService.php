<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\User;
use App\Support\SearchQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function record(string $event, ?User $user = null, ?array $properties = null, ?string $sessionId = null): void
    {
        AnalyticsEvent::query()->create([
            'event' => $event,
            'user_id' => $user?->id,
            'properties' => $properties,
            'session_id' => $sessionId,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return array{
     *     registrations: int,
     *     logins: int,
     *     reviews_submitted: int,
     *     forum_topics: int,
     *     forum_posts: int,
     *     search_queries: int
     * }
     */
    public function summaryForDays(int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);

        $counts = AnalyticsEvent::query()
            ->where('occurred_at', '>=', $since)
            ->select('event', DB::raw('count(*) as total'))
            ->groupBy('event')
            ->pluck('total', 'event');

        return [
            'registrations' => (int) ($counts['user.registered'] ?? 0),
            'logins' => (int) ($counts['user.login'] ?? 0),
            'reviews_submitted' => (int) ($counts['review.submitted'] ?? 0),
            'forum_topics' => (int) ($counts['forum.topic_created'] ?? 0),
            'forum_posts' => (int) ($counts['forum.post_created'] ?? 0),
            'search_queries' => (int) ($counts['search.query'] ?? 0),
        ];
    }

    /**
     * @return Collection<int, object{day: string, total: int}>
     */
    public function eventCountByDay(string $event, int $days = 14): Collection
    {
        $since = Carbon::now()->subDays($days)->startOfDay();

        return AnalyticsEvent::query()
            ->where('event', $event)
            ->where('occurred_at', '>=', $since)
            ->selectRaw('date(occurred_at) as day, count(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    /**
     * @return Collection<int, object{day: string, total: int}>
     */
    public function registrationsByDay(int $days = 14): Collection
    {
        return $this->eventCountByDay('user.registered', $days);
    }

    /**
     * @return list<array{query: string, total: int}>
     */
    /**
     * Aggregated in SQL rather than by walking every matching row into PHP, which
     * is what the admin dashboard used to do on each page load.
     *
     * Note the engine difference this exposes: SQLite's lower() is ASCII-only
     * while PostgreSQL's is locale-aware, so Cyrillic queries case-fold in
     * production but not in local SQLite. Do not "fix" that by moving the folding
     * back into PHP — it would reintroduce the full-table scan.
     */
    public function topSearchQueries(int $days = 30, int $limit = 10): array
    {
        $since = Carbon::now()->subDays($days)->startOfDay();
        $connection = DB::connection();

        $extract = $connection->getDriverName() === 'pgsql'
            ? "properties->>'q'"
            : "json_extract(properties, '$.q')";

        $normalized = "lower(trim({$extract}))";

        return $connection->table('analytics_events')
            ->where('event', 'search.query')
            ->where('occurred_at', '>=', $since)
            ->whereRaw("{$extract} is not null")
            ->whereRaw("length(trim({$extract})) >= ?", [SearchQuery::MIN_LENGTH])
            ->selectRaw("{$normalized} as query, count(*) as total")
            ->groupByRaw($normalized)
            ->orderByDesc('total')
            ->orderBy('query')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'query' => (string) $row->query,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    public function countDirectoryPublished(): array
    {
        return [
            'doctors' => \App\Models\Doctor::query()->published()->count(),
            'facilities' => \App\Models\Facility::query()->clinical()->published()->count(),
            'reviews_pending' => \App\Models\Review::query()->where('status', 'pending')->count(),
            'forum_topics_pending' => \App\Models\ForumTopic::query()->where('status', 'pending')->count(),
        ];
    }
}
