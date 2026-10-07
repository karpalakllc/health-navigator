<?php

namespace App\Support\Levels;

use App\Enums\ForumContentStatus;
use App\Enums\ReviewStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Points from public content (docs/levels.md), for some members or for
 * everyone, over all time or within a window (a calendar month for the top
 * lists). One code path for both, so a member's level and the monthly lists
 * can never disagree about what counts.
 *
 * - Content counts while it is approved; within a window it counts in the
 *   month it was published.
 * - The per-day caps go by the day it was submitted (created_at): a
 *   moderator approving a backlog at once does not cut anyone's points.
 * - „Корисно“ votes count from other, non-suspended members only, at most
 *   HELPFUL_PER_ITEM per item and HELPFUL_PER_VOTER_PER_AUTHOR from one
 *   member for one author, in the order they were cast.
 * - Content taken down after publication subtracts a penalty (in the month
 *   it was taken down). Totals never go below zero.
 *
 * @phpstan-type ReviewTally array{reviews: int, helpful: int, removed: int, points: int}
 * @phpstan-type ForumTally array{topics: int, replies: int, helpful: int, removed: int, points: int}
 */
final class ContributorScoring
{
    /**
     * @param  list<int>|null  $userIds  null: everyone
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}|null  $window  [start, end), null: all time
     * @return array<int, ReviewTally>
     */
    public static function reviews(?array $userIds = null, ?array $window = null): array
    {
        /** @var array<int, ReviewTally> $tallies */
        $tallies = [];
        $blank = ['reviews' => 0, 'helpful' => 0, 'removed' => 0, 'points' => 0];
        $counted = [];

        $published = DB::table('reviews')
            ->where('status', ReviewStatus::Approved->value)
            ->select(['user_id', 'created_at']);
        self::scope($published, 'user_id', $userIds, 'published_at', $window);

        foreach (self::cappedPerDay($published->get(), LevelRules::REVIEWS_PER_DAY) as $userId => [$all, $capped]) {
            $tallies[$userId] = ['reviews' => $all] + ($tallies[$userId] ?? $blank);
            $counted[$userId] = $capped;
        }

        $votes = DB::table('review_helpful_votes as v')
            ->join('reviews as r', 'r.id', '=', 'v.review_id')
            ->join('users as voter', 'voter.id', '=', 'v.user_id')
            ->where('r.status', ReviewStatus::Approved->value)
            ->whereColumn('v.user_id', '!=', 'r.user_id')
            ->whereNull('voter.suspended_at')
            ->orderBy('v.created_at')
            ->orderBy('v.id')
            ->select(['v.review_id as item_id', 'v.user_id as voter_id', 'r.user_id as author_id']);
        self::scope($votes, 'r.user_id', $userIds, 'v.created_at', $window);

        foreach (self::cappedVotes($votes->get()) as $userId => $helpful) {
            $tallies[$userId] = ['helpful' => $helpful] + ($tallies[$userId] ?? $blank);
        }

        $removed = DB::table('reviews')
            ->whereNotNull('removed_at')
            ->select('user_id', DB::raw('count(*) as aggregate'))
            ->groupBy('user_id');
        self::scope($removed, 'user_id', $userIds, 'removed_at', $window);

        foreach ($removed->get() as $row) {
            $tallies[(int) $row->user_id] = ['removed' => (int) $row->aggregate] + ($tallies[(int) $row->user_id] ?? $blank);
        }

        foreach ($tallies as $userId => $tally) {
            $tallies[$userId]['points'] = max(0,
                ($counted[$userId] ?? 0) * LevelRules::REVIEW_POINTS
                + $tally['helpful'] * LevelRules::REVIEW_HELPFUL_POINTS
                - $tally['removed'] * LevelRules::REVIEW_REMOVED_PENALTY,
            );
        }

        return $tallies;
    }

    /**
     * @param  list<int>|null  $userIds  null: everyone
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}|null  $window  [start, end), null: all time
     * @return array<int, ForumTally>
     */
    public static function forum(?array $userIds = null, ?array $window = null): array
    {
        /** @var array<int, ForumTally> $tallies */
        $tallies = [];
        $blank = ['topics' => 0, 'replies' => 0, 'helpful' => 0, 'removed' => 0, 'points' => 0];
        $topicPoints = [];
        $replyPoints = [];

        $topics = DB::table('forum_topics')
            ->where('status', ForumContentStatus::Approved->value)
            ->select(['user_id', 'created_at']);
        self::scope($topics, 'user_id', $userIds, 'published_at', $window);

        foreach (self::cappedPerDay($topics->get(), LevelRules::TOPICS_PER_DAY) as $userId => [$all, $capped]) {
            $tallies[$userId] = ['topics' => $all] + ($tallies[$userId] ?? $blank);
            $topicPoints[$userId] = $capped * LevelRules::TOPIC_POINTS;
        }

        // Replies in a published topic opened by someone else.
        $replies = DB::table('forum_posts as p')
            ->join('forum_topics as t', 't.id', '=', 'p.forum_topic_id')
            ->where('p.status', ForumContentStatus::Approved->value)
            ->where('t.status', ForumContentStatus::Approved->value)
            ->whereColumn('p.user_id', '!=', 't.user_id')
            ->select(['p.user_id', 'p.created_at']);
        self::scope($replies, 'p.user_id', $userIds, 'p.published_at', $window);

        foreach (self::cappedPerDay($replies->get(), LevelRules::REPLIES_PER_DAY) as $userId => [$all, $capped]) {
            $tallies[$userId] = ['replies' => $all] + ($tallies[$userId] ?? $blank);
            $replyPoints[$userId] = $capped * LevelRules::REPLY_POINTS;
        }

        $votes = DB::table('forum_post_helpful_votes as v')
            ->join('forum_posts as p', 'p.id', '=', 'v.forum_post_id')
            ->join('forum_topics as t', 't.id', '=', 'p.forum_topic_id')
            ->join('users as voter', 'voter.id', '=', 'v.user_id')
            ->where('p.status', ForumContentStatus::Approved->value)
            ->where('t.status', ForumContentStatus::Approved->value)
            ->whereColumn('v.user_id', '!=', 'p.user_id')
            ->whereNull('voter.suspended_at')
            ->orderBy('v.created_at')
            ->orderBy('v.id')
            ->select(['v.forum_post_id as item_id', 'v.user_id as voter_id', 'p.user_id as author_id']);
        self::scope($votes, 'p.user_id', $userIds, 'v.created_at', $window);

        foreach (self::cappedVotes($votes->get()) as $userId => $helpful) {
            $tallies[$userId] = ['helpful' => $helpful] + ($tallies[$userId] ?? $blank);
        }

        foreach (['forum_topics', 'forum_posts'] as $table) {
            $removed = DB::table($table)
                ->whereNotNull('removed_at')
                ->select('user_id', DB::raw('count(*) as aggregate'))
                ->groupBy('user_id');
            self::scope($removed, 'user_id', $userIds, 'removed_at', $window);

            foreach ($removed->get() as $row) {
                $userId = (int) $row->user_id;
                $tally = $tallies[$userId] ?? $blank;
                $tally['removed'] += (int) $row->aggregate;
                $tallies[$userId] = $tally;
            }
        }

        foreach ($tallies as $userId => $tally) {
            $tallies[$userId]['points'] = max(0,
                ($topicPoints[$userId] ?? 0)
                + ($replyPoints[$userId] ?? 0)
                + $tally['helpful'] * LevelRules::REPLY_HELPFUL_POINTS
                - $tally['removed'] * LevelRules::FORUM_REMOVED_PENALTY,
            );
        }

        return $tallies;
    }

    /**
     * The calendar month (Macedonian time) as a UTC [start, end) window.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function monthWindow(CarbonImmutable $month): array
    {
        $start = $month->setTimezone(LevelRules::TIMEZONE)->startOfMonth();

        return [$start->utc(), $start->addMonth()->utc()];
    }

    /**
     * @param  list<int>|null  $userIds
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}|null  $window
     */
    private static function scope(Builder $query, string $userColumn, ?array $userIds, string $dateColumn, ?array $window): void
    {
        if ($userIds !== null) {
            $query->whereIn($userColumn, $userIds);
        }

        if ($window !== null) {
            $query->where($dateColumn, '>=', $window[0])->where($dateColumn, '<', $window[1]);
        }
    }

    /**
     * Per author: [all items, items counted after the per-day cap].
     *
     * @param  iterable<object>  $rows  user_id + created_at
     * @return array<int, array{0: int, 1: int}>
     */
    private static function cappedPerDay(iterable $rows, int $perDay): array
    {
        $perUserDay = [];

        foreach ($rows as $row) {
            $day = Carbon::parse($row->created_at, 'UTC')->setTimezone(LevelRules::TIMEZONE)->toDateString();
            $perUserDay[(int) $row->user_id][$day] = ($perUserDay[(int) $row->user_id][$day] ?? 0) + 1;
        }

        $result = [];

        foreach ($perUserDay as $userId => $days) {
            $result[$userId] = [
                array_sum($days),
                array_sum(array_map(fn (int $count): int => min($count, $perDay), $days)),
            ];
        }

        return $result;
    }

    /**
     * Votes counted per author after the per-item and per-voter caps.
     *
     * @param  iterable<object>  $votes  item_id, voter_id, author_id, oldest first
     * @return array<int, int>
     */
    private static function cappedVotes(iterable $votes): array
    {
        $perItem = [];
        $perPair = [];
        $counted = [];

        foreach ($votes as $vote) {
            $item = (int) $vote->item_id;
            $author = (int) $vote->author_id;
            $pair = $author.':'.(int) $vote->voter_id;

            if (($perItem[$item] ?? 0) >= LevelRules::HELPFUL_PER_ITEM
                || ($perPair[$pair] ?? 0) >= LevelRules::HELPFUL_PER_VOTER_PER_AUTHOR) {
                continue;
            }

            $perItem[$item] = ($perItem[$item] ?? 0) + 1;
            $perPair[$pair] = ($perPair[$pair] ?? 0) + 1;
            $counted[$author] = ($counted[$author] ?? 0) + 1;
        }

        return $counted;
    }
}
