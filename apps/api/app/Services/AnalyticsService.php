<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\User;
use App\Support\SearchQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * How long the admin dashboard's event aggregates are reused, in seconds.
     *
     * Each one scans a window of analytics_events, and the dashboard used to
     * re-run all of them every 5 seconds per open tab. New events are not
     * flushed in: the figures are trends over days, so being up to five minutes
     * behind is fine. Directory and moderation counts are not cached here.
     */
    public const AGGREGATE_TTL_SECONDS = 300;

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
        $counts = Cache::remember(
            "analytics:summary:{$days}",
            self::AGGREGATE_TTL_SECONDS,
            fn (): array => AnalyticsEvent::query()
                ->where('occurred_at', '>=', Carbon::now()->subDays($days))
                ->select('event', DB::raw('count(*) as total'))
                ->groupBy('event')
                ->pluck('total', 'event')
                ->all(),
        );

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
        // Plain rows rather than AnalyticsEvent models, so the cache holds data
        // and not serialised Eloquent objects.
        $rows = Cache::remember(
            "analytics:by-day:{$event}:{$days}",
            self::AGGREGATE_TTL_SECONDS,
            fn (): array => AnalyticsEvent::query()
                ->where('event', $event)
                ->where('occurred_at', '>=', Carbon::now()->subDays($days)->startOfDay())
                ->selectRaw('date(occurred_at) as day, count(*) as total')
                ->groupBy('day')
                ->orderBy('day')
                ->toBase()
                ->get()
                ->map(fn (object $row): array => ['day' => (string) $row->day, 'total' => (int) $row->total])
                ->all(),
        );

        return collect($rows)->map(fn (array $row): object => (object) $row);
    }

    /**
     * @return Collection<int, object{day: string, total: int}>
     */
    public function registrationsByDay(int $days = 14): Collection
    {
        return $this->eventCountByDay('user.registered', $days);
    }

    /**
     * Aggregated in SQL rather than by walking every matching row into PHP, which
     * is what the admin dashboard used to do on each page load.
     *
     * Note the engine difference this exposes: SQLite's lower() is ASCII-only
     * while PostgreSQL's is locale-aware, so Cyrillic queries case-fold in
     * production but not in local SQLite. Do not "fix" that by moving the folding
     * back into PHP — it would reintroduce the full-table scan.
     *
     * @return list<array{query: string, total: int}>
     */
    public function topSearchQueries(int $days = 30, int $limit = 10): array
    {
        return Cache::remember(
            "analytics:top-searches:{$days}:{$limit}",
            self::AGGREGATE_TTL_SECONDS,
            fn (): array => $this->queryTopSearchQueries($days, $limit),
        );
    }

    /**
     * @return list<array{query: string, total: int}>
     */
    private function queryTopSearchQueries(int $days, int $limit): array
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
            'doctors' => Doctor::query()->published()->count(),
            'facilities' => Facility::query()->clinical()->published()->count(),
            'reviews_pending' => Review::query()->where('status', 'pending')->count(),
            'forum_topics_pending' => ForumTopic::query()->where('status', 'pending')->count(),
        ];
    }
}
