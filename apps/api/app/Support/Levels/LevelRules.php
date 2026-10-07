<?php

namespace App\Support\Levels;

/**
 * The contributor-level rules in one place (docs/levels.md describes them
 * for people; keep the two in step). Titles live in the web app
 * (levels.reviewLevelN / levels.forumLevelN in mk.ts): the API only says the number.
 *
 * Points come from approved, public content only. Anything else — pending
 * or refused content, a member's own votes, votes from suspended accounts —
 * earns nothing, and content taken down after publication costs more than it
 * earned, so posting something that gets removed never pays off.
 */
final class LevelRules
{
    /** Month boundaries and the per-day caps follow Macedonian time. */
    public const TIMEZONE = 'Europe/Skopje';

    // Reviews („Најкорисни рецензенти“).
    public const REVIEW_POINTS = 10;

    public const REVIEW_HELPFUL_POINTS = 2;

    public const REVIEW_REMOVED_PENALTY = 20;

    /** Reviews counted for points per day of submission. */
    public const REVIEWS_PER_DAY = 5;

    // Forum („Најактивни во форумот“).
    public const TOPIC_POINTS = 3;

    /** A reply in someone else's topic; replies in one's own topic earn nothing. */
    public const REPLY_POINTS = 5;

    public const REPLY_HELPFUL_POINTS = 3;

    public const FORUM_REMOVED_PENALTY = 15;

    public const TOPICS_PER_DAY = 3;

    public const REPLIES_PER_DAY = 8;

    // Votes, both kinds.
    /** „Корисно“ votes counted per review or reply. */
    public const HELPFUL_PER_ITEM = 10;

    /** „Корисно“ votes counted from one member for one author, all items together. */
    public const HELPFUL_PER_VOTER_PER_AUTHOR = 3;

    /**
     * Review ladder: level => [published reviews, points], both needed.
     *
     * @var array<int, array{0: int, 1: int}>
     */
    public const REVIEW_LADDER = [
        1 => [1, 10],    // Рецензент
        2 => [3, 35],    // Активен рецензент
        3 => [6, 80],    // Посветен рецензент
        4 => [12, 170],  // Искусен рецензент
        5 => [25, 380],  // Столб на заедницата
    ];

    /**
     * Forum ladder: level => [posts, points], both needed. Posts are the
     * member's published topics plus their published replies in other
     * members' topics, at every level (a reply in one's own topic counts
     * nothing); answering earns more points than asking (REPLY_POINTS).
     *
     * @var array<int, array{0: int, 1: int}>
     */
    public const FORUM_LADDER = [
        1 => [1, 3],     // Соговорник
        2 => [5, 35],    // Помошник
        3 => [20, 140],  // Посветен помошник
        4 => [50, 380],  // Стожер на форумот
    ];

    /** Top lists show at most this many members. */
    public const LEADERBOARD_SIZE = 10;

    /**
     * The rules as the public page explains them, so the web never keeps
     * its own copy of the numbers.
     *
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        $ladder = fn (array $ladder, string $countKey): array => array_map(
            fn (int $level, array $requirement): array => ['level' => $level, $countKey => $requirement[0], 'points' => $requirement[1]],
            array_keys($ladder),
            array_values($ladder),
        );

        return [
            'reviews' => [
                'review_points' => self::REVIEW_POINTS,
                'helpful_points' => self::REVIEW_HELPFUL_POINTS,
                'removed_penalty' => self::REVIEW_REMOVED_PENALTY,
                'reviews_per_day' => self::REVIEWS_PER_DAY,
                'ladder' => $ladder(self::REVIEW_LADDER, 'reviews'),
            ],
            'forum' => [
                'topic_points' => self::TOPIC_POINTS,
                'reply_points' => self::REPLY_POINTS,
                'helpful_points' => self::REPLY_HELPFUL_POINTS,
                'removed_penalty' => self::FORUM_REMOVED_PENALTY,
                'topics_per_day' => self::TOPICS_PER_DAY,
                'replies_per_day' => self::REPLIES_PER_DAY,
                'ladder' => $ladder(self::FORUM_LADDER, 'posts'),
            ],
            'helpful_per_item' => self::HELPFUL_PER_ITEM,
            'helpful_per_voter_per_author' => self::HELPFUL_PER_VOTER_PER_AUTHOR,
            'leaderboard_size' => self::LEADERBOARD_SIZE,
        ];
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $ladder
     */
    public static function levelFor(array $ladder, int $count, int $points): int
    {
        $level = 0;

        foreach ($ladder as $candidate => [$minCount, $minPoints]) {
            if ($count >= $minCount && $points >= $minPoints) {
                $level = $candidate;
            }
        }

        return $level;
    }

    /**
     * What the next level still needs, or null at the top.
     *
     * @param  array<int, array{0: int, 1: int}>  $ladder
     * @return array{level: int, missing_count: int, missing_points: int}|null
     */
    public static function nextStep(array $ladder, int $level, int $count, int $points): ?array
    {
        $next = $level + 1;

        if (! isset($ladder[$next])) {
            return null;
        }

        [$minCount, $minPoints] = $ladder[$next];

        return [
            'level' => $next,
            'missing_count' => max(0, $minCount - $count),
            'missing_points' => max(0, $minPoints - $points),
        ];
    }
}
