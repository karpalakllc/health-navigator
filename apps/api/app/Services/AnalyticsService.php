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
     * Read from the daily aggregates. Terms are normalised in PHP on the way in,
     * so Cyrillic case-folds the same on SQLite and PostgreSQL.
     *
     * @return list<array{query: string, total: int}>
     */
    public function topSearchQueries(int $days = 30, int $limit = 10): array
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
