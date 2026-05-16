<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\User;
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
    public function registrationsByDay(int $days = 14): Collection
    {
        $since = Carbon::now()->subDays($days)->startOfDay();

        return AnalyticsEvent::query()
            ->where('event', 'user.registered')
            ->where('occurred_at', '>=', $since)
            ->selectRaw('date(occurred_at) as day, count(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
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
