<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTopic;
use App\Models\Review;
use App\Models\SearchTermDaily;
use App\Models\User;
use App\Support\SearchTermNormalizer;
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
     * Count one search in today's aggregate row for its normalised term.
     *
     * Searches are NOT analytics events: no user, session or exact time is kept
     * (see SearchTermDaily). One upsert, so concurrent searches for the same
     * term both count.
     */
    public function recordSearchTerm(?string $query): void
    {
        $term = SearchTermNormalizer::normalize($query);

        if ($term === null) {
            return;
        }

        DB::table('search_term_daily')->upsert(
            [['date' => Carbon::today()->toDateString(), 'term' => $term, 'count' => 1]],
            ['date', 'term'],
            ['count' => DB::raw('search_term_daily.count + 1')],
        );
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
            // Day granularity: the aggregates have no time of day.
            'search_queries' => (int) SearchTermDaily::query()
                ->where('date', '>=', $since->toDateString())
                ->sum('count'),
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
     * Read from the daily aggregates. Terms are normalised in PHP on the way in,
     * so Cyrillic case-folds the same on SQLite and PostgreSQL.
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
        return SearchTermDaily::query()
            ->toBase()
            ->where('date', '>=', Carbon::now()->subDays($days)->toDateString())
            ->selectRaw('term, sum(count) as total')
            ->groupBy('term')
            ->orderByDesc('total')
            ->orderBy('term')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'query' => (string) $row->term,
                'total' => (int) $row->total,
            ])
            ->values()
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
