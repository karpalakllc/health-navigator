<?php

namespace App\Support\Notifications;

use App\Enums\ForumContentStatus;
use App\Enums\ReviewResponseStatus;
use App\Enums\ReviewStatus;
use App\Models\ForumPost;
use App\Models\Review;
use App\Models\User;
use App\Support\Levels\LevelRules;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * What a member's published content did (W8-B): the monthly digest's
 * figures and the totals on „Мои рецензии“. Counts only, about the member's
 * own approved content; never who viewed or voted.
 */
final class ImpactStats
{
    /**
     * One calendar month.
     *
     * @return array{review_views: int, helpful_votes: int, replies: int, forum_answers: int, forum_replies_received: int, forum_helpful_votes: int, review_views_total: int}
     */
    public static function forMonth(User $user, CarbonInterface $month): array
    {
        // The calendar month in Macedonian time (as the levels and the
        // monthly view buckets use), compared as UTC instants.
        $local = CarbonImmutable::create($month->year, $month->month, 1, 0, 0, 0, LevelRules::TIMEZONE);
        $start = $local->utc();
        $end = $local->endOfMonth()->utc();
        $reviewIds = Review::query()
            ->where('user_id', $user->getKey())
            ->where('status', ReviewStatus::Approved)
            ->pluck('id');

        return [
            'review_views' => (int) DB::table('review_views_monthly')
                ->whereIn('review_id', $reviewIds)
                ->where('month', $local->toDateString())
                ->sum('views'),
            'helpful_votes' => DB::table('review_helpful_votes')
                ->whereIn('review_id', $reviewIds)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'replies' => Review::query()
                ->whereIn('id', $reviewIds)
                ->whereNotNull('response_body')
                ->where('response_status', ReviewResponseStatus::Approved)
                ->whereBetween('response_at', [$start, $end])
                ->count(),
            'forum_answers' => ForumPost::query()
                ->where('user_id', $user->getKey())
                ->where('status', ForumContentStatus::Approved)
                ->whereBetween('published_at', [$start, $end])
                ->count(),
            'forum_replies_received' => ForumPost::query()
                ->where('status', ForumContentStatus::Approved)
                ->where('user_id', '!=', $user->getKey())
                ->whereBetween('published_at', [$start, $end])
                ->whereHas('topic', fn ($topic) => $topic
                    ->where('user_id', $user->getKey())
                    ->where('status', ForumContentStatus::Approved))
                ->count(),
            // W8-C „Корисно“ on the member's published forum replies, given by
            // others this month (self-votes are not possible; the count only).
            'forum_helpful_votes' => DB::table('forum_post_helpful_votes')
                ->join('forum_posts', 'forum_posts.id', '=', 'forum_post_helpful_votes.forum_post_id')
                ->where('forum_posts.user_id', $user->getKey())
                ->where('forum_posts.status', ForumContentStatus::Approved->value)
                ->where('forum_post_helpful_votes.user_id', '!=', $user->getKey())
                ->whereBetween('forum_post_helpful_votes.created_at', [$start, $end])
                ->count(),
            'review_views_total' => (int) Review::query()->whereIn('id', $reviewIds)->sum('view_count'),
        ];
    }

    /**
     * All-time totals over the member's published reviews, for „Мои рецензии“.
     *
     * @return array{views: int, helpful: int, replies: int, published: int}
     */
    public static function reviewTotals(User $user): array
    {
        $row = Review::query()
            ->where('user_id', $user->getKey())
            ->where('status', ReviewStatus::Approved)
            ->toBase()
            ->selectRaw('count(*) as published, coalesce(sum(view_count), 0) as views, coalesce(sum(helpful_count), 0) as helpful')
            ->selectRaw("sum(case when response_body is not null and response_status = 'approved' then 1 else 0 end) as replies")
            ->first();

        return [
            'views' => (int) ($row->views ?? 0),
            'helpful' => (int) ($row->helpful ?? 0),
            'replies' => (int) ($row->replies ?? 0),
            'published' => (int) ($row->published ?? 0),
        ];
    }

    /** @param array<string, int> $stats */
    public static function isEmpty(array $stats): bool
    {
        foreach ($stats as $key => $value) {
            if ($key !== 'review_views_total' && $value > 0) {
                return false;
            }
        }

        return true;
    }
}
