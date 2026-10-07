<?php

namespace App\Support\Levels;

use App\Models\ContributorLevel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Stores members' levels and builds the monthly top lists (docs/levels.md).
 *
 * Recomputed for the author whenever their content is approved, refused or
 * taken down and whenever one of their items gains or loses a „Корисно“
 * vote, and for everyone nightly (levels:recompute), so a missed event heals
 * within a day.
 */
final class ContributorLevels
{
    /** Top lists are cached this long; the nightly run clears them too. */
    public const LEADERBOARD_TTL_SECONDS = 6 * 3600;

    /**
     * Recompute one member's levels. Runs after the surrounding transaction
     * commits, so it always sees the content that triggered it.
     */
    public static function refreshAfterCommit(int|string|null $userId): void
    {
        if ($userId === null) {
            return;
        }

        DB::afterCommit(static fn () => self::recompute((int) $userId));
    }

    public static function recompute(int $userId): ?ContributorLevel
    {
        return self::recomputeMany([$userId])[$userId] ?? null;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, ContributorLevel>
     */
    public static function recomputeMany(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $reviews = ContributorScoring::reviews($userIds);
        $forum = ContributorScoring::forum($userIds);
        $levels = [];
        // A deleted account keeps its public posts but no recognition.
        $anonymised = User::query()->whereKey($userIds)->whereNotNull('anonymised_at')->pluck('id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true]);

        foreach ($userIds as $userId) {
            $review = $reviews[$userId] ?? null;
            $post = $forum[$userId] ?? null;

            // Nothing published, nothing taken down: no row at all.
            if (($review === null && $post === null) || $anonymised->has($userId)) {
                ContributorLevel::query()->whereKey($userId)->delete();

                continue;
            }

            $reviewsCount = $review['reviews'] ?? 0;
            $reviewPoints = $review['points'] ?? 0;
            $forumPosts = ($post['topics'] ?? 0) + ($post['replies'] ?? 0);
            $forumPoints = $post['points'] ?? 0;

            $levels[$userId] = ContributorLevel::query()->updateOrCreate(['user_id' => $userId], [
                'review_points' => $reviewPoints,
                'reviews_count' => $reviewsCount,
                'review_helpful_count' => $review['helpful'] ?? 0,
                'review_level' => LevelRules::levelFor(LevelRules::REVIEW_LADDER, $reviewsCount, $reviewPoints),
                'forum_points' => $forumPoints,
                'forum_topics_count' => $post['topics'] ?? 0,
                'forum_replies_count' => $post['replies'] ?? 0,
                'forum_helpful_count' => $post['helpful'] ?? 0,
                'forum_level' => LevelRules::levelFor(LevelRules::FORUM_LADDER, $forumPosts, $forumPoints),
                'computed_at' => now(),
            ]);
        }

        return $levels;
    }

    /**
     * Everyone who has ever published content, in batches; rows of members
     * with nothing left are dropped. Returns how many members were scored.
     */
    public static function recomputeAll(): int
    {
        $authors = DB::table('reviews')->select('user_id')
            ->union(DB::table('forum_topics')->select('user_id'))
            ->union(DB::table('forum_posts')->select('user_id'))
            ->union(DB::table('contributor_levels')->select('user_id'));

        $ids = DB::query()->fromSub($authors, 'authors')->orderBy('user_id')->pluck('user_id')
            ->map(fn ($id): int => (int) $id);

        foreach ($ids->chunk(200) as $chunk) {
            self::recomputeMany($chunk->values()->all());
        }

        self::forgetLeaderboards();

        return $ids->count();
    }

    /**
     * The member's own view: both ladders with what the next level needs.
     *
     * @return array<string, mixed>
     */
    public static function progressFor(User $user): array
    {
        $row = self::recompute((int) $user->getKey());
        $reviewsCount = $row->reviews_count ?? 0;
        $reviewPoints = $row->review_points ?? 0;
        $reviewLevel = $row->review_level ?? 0;
        $forumPosts = ($row->forum_topics_count ?? 0) + ($row->forum_replies_count ?? 0);
        $forumPoints = $row->forum_points ?? 0;
        $forumLevel = $row->forum_level ?? 0;

        return [
            // False for staff, suspended accounts and temporary usernames: the
            // chip is not shown next to their name, whatever they earned.
            'shown_publicly' => $user->showsContributorLevel(),
            'reviews' => [
                'level' => $reviewLevel,
                'points' => $reviewPoints,
                'reviews' => $reviewsCount,
                'helpful' => $row->review_helpful_count ?? 0,
                'next' => self::next(LevelRules::nextStep(LevelRules::REVIEW_LADDER, $reviewLevel, $reviewsCount, $reviewPoints), 'reviews'),
            ],
            'forum' => [
                'level' => $forumLevel,
                'points' => $forumPoints,
                'topics' => $row->forum_topics_count ?? 0,
                'replies' => $row->forum_replies_count ?? 0,
                'helpful' => $row->forum_helpful_count ?? 0,
                'next' => self::next(LevelRules::nextStep(LevelRules::FORUM_LADDER, $forumLevel, $forumPosts, $forumPoints), 'posts'),
            ],
        ];
    }

    /**
     * The calendar month before $now (Macedonian time): „Најкорисни
     * рецензенти“ and „Најактивни во форумот“, top LEADERBOARD_SIZE each, by
     * username only. The scores are cached (user ids and numbers only); who
     * is shown and under which username is decided on every read, so an
     * erased, suspended or renamed member drops out or shows the new name at
     * once.
     *
     * @return array{month: string, reviewers: list<array<string, mixed>>, forum: list<array<string, mixed>>}
     */
    public static function leaderboards(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $month = $now->setTimezone(LevelRules::TIMEZONE)->startOfMonth()->subMonthNoOverflow();
        $key = self::leaderboardKey($month);

        /** @var array{month: string, reviewers: list<array{user_id: int, points: int, helpful: int, stats: array<string, int>}>, forum: list<array{user_id: int, points: int, helpful: int, stats: array<string, int>}>} $scores */
        $scores = Cache::remember($key, self::LEADERBOARD_TTL_SECONDS, function () use ($month): array {
            $window = ContributorScoring::monthWindow($month);

            return [
                'month' => $month->format('Y-m'),
                'reviewers' => self::scores(ContributorScoring::reviews(null, $window), fn (array $tally): array => [
                    'reviews' => $tally['reviews'],
                    'helpful' => $tally['helpful'],
                ]),
                'forum' => self::scores(ContributorScoring::forum(null, $window), fn (array $tally): array => [
                    'topics' => $tally['topics'],
                    'replies' => $tally['replies'],
                    'helpful' => $tally['helpful'],
                ]),
            ];
        });

        return [
            'month' => $scores['month'],
            'reviewers' => self::rank($scores['reviewers'], 'review'),
            'forum' => self::rank($scores['forum'], 'forum'),
        ];
    }

    /**
     * The level shown as a chip next to a member's name on their reviews
     * („review“) or forum posts („forum“), or null when there is none to show.
     * Callers eager-load user.contributorLevel.
     *
     * @param  'review'|'forum'  $ladder
     */
    public static function publicLevel(?User $user, string $ladder): ?int
    {
        if ($user === null || ! $user->showsContributorLevel()) {
            return null;
        }

        $level = self::storedLevel($user, $ladder);

        return $level > 0 ? $level : null;
    }

    /**
     * @param  'review'|'forum'  $ladder
     */
    private static function storedLevel(User $user, string $ladder): int
    {
        $row = $user->contributorLevel;

        if ($row === null) {
            return 0;
        }

        return $ladder === 'forum' ? $row->forum_level : $row->review_level;
    }

    public static function forgetLeaderboards(): void
    {
        $month = CarbonImmutable::now()->setTimezone(LevelRules::TIMEZONE)->startOfMonth()->subMonthNoOverflow();
        Cache::forget(self::leaderboardKey($month));
    }

    private static function leaderboardKey(CarbonImmutable $month): string
    {
        // v2: scores only (the v1 entries held usernames).
        return 'levels:leaderboards:v2:'.$month->format('Y-m');
    }

    /**
     * The cacheable part of a board: user ids and numbers, no names.
     *
     * @template T of array{points: int, helpful: int}
     *
     * @param  array<int, T>  $tallies
     * @param  callable(T): array<string, int>  $stats
     * @return list<array{user_id: int, points: int, helpful: int, stats: array<string, int>}>
     */
    private static function scores(array $tallies, callable $stats): array
    {
        $scores = [];

        foreach ($tallies as $userId => $tally) {
            if ($tally['points'] > 0) {
                $scores[] = ['user_id' => (int) $userId, 'points' => $tally['points'], 'helpful' => $tally['helpful'], 'stats' => $stats($tally)];
            }
        }

        return $scores;
    }

    /**
     * The board as shown: members who may be shown now, under their current
     * username.
     *
     * @param  list<array{user_id: int, points: int, helpful: int, stats: array<string, int>}>  $scores
     * @param  'review'|'forum'  $ladder
     * @return list<array<string, mixed>>
     */
    private static function rank(array $scores, string $ladder): array
    {
        if ($scores === []) {
            return [];
        }

        $users = User::query()
            ->whereKey(array_column($scores, 'user_id'))
            ->with('contributorLevel')
            ->get()
            ->filter(fn (User $user): bool => $user->showsContributorLevel())
            ->keyBy('id');

        $rows = [];

        foreach ($scores as $score) {
            $user = $users->get($score['user_id']);

            if ($user === null) {
                continue;
            }

            $rows[] = [
                'username' => (string) $user->username,
                'level' => self::storedLevel($user, $ladder),
                'points' => $score['points'],
                ...$score['stats'],
                '_helpful' => $score['helpful'],
            ];
        }

        // Points, then „Корисно“ votes, then the name, so equal scores keep a
        // stable order.
        usort($rows, fn (array $a, array $b): int => [$b['points'], $b['_helpful'], $a['username']]
            <=> [$a['points'], $a['_helpful'], $b['username']]);

        return array_map(function (array $row): array {
            unset($row['_helpful']);

            return $row;
        }, array_slice($rows, 0, LevelRules::LEADERBOARD_SIZE));
    }

    /**
     * @param  array{level: int, missing_count: int, missing_points: int}|null  $step
     * @return array<string, int>|null
     */
    private static function next(?array $step, string $countKey): ?array
    {
        if ($step === null) {
            return null;
        }

        return [
            'level' => $step['level'],
            'missing_'.$countKey => $step['missing_count'],
            'missing_points' => $step['missing_points'],
        ];
    }
}
