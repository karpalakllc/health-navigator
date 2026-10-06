<?php

namespace App\Support;

use App\Enums\ForumContentStatus;
use App\Enums\RemovalCategory;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The public moderation figures on /transparency: per calendar month (UTC)
 * over the last twelve months, for reviews, the forum (topics and replies
 * together) and reports. Only counts and averages — never content, authors,
 * reporters or moderators.
 *
 * - received: submitted that month (any outcome);
 * - published: made public that month (later removals included);
 * - rejected: refused before ever being published, by decision date;
 * - removed: taken down after publication, by removal date and public category;
 * - average_moderation_hours: submission to a moderator's first decision
 *   (approval, or refusal before publication), by decision date; content
 *   published without a moderator (forum pre-moderation off) is left out.
 *
 * Computed with grouped queries and cached for CACHE_SECONDS; the page says
 * the figures refresh hourly.
 */
final class TransparencyStats
{
    public const CACHE_KEY = 'transparency:stats:v1';

    public const CACHE_SECONDS = 3600;

    public const MONTHS = 12;

    /**
     * @return array<string, mixed>
     */
    public static function cached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => self::compute());
    }

    /**
     * @return array{generated_at: string, months: list<array<string, mixed>>}
     */
    public static function compute(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $from = $now->startOfMonth()->subMonths(self::MONTHS - 1);
        $to = $now->startOfMonth()->addMonth();

        $reviews = self::contentStats(['reviews'], ReviewStatus::Pending->value, ReviewStatus::Rejected->value, $from, $to);
        $forum = self::contentStats(['forum_topics', 'forum_posts'], ForumContentStatus::Pending->value, ForumContentStatus::Rejected->value, $from, $to);

        $reportsReceived = self::countByMonth('content_reports', 'created_at', $from, $to);
        $reportsHidden = self::countByMonth('content_reports', 'resolved_at', $from, $to, fn (Builder $q) => $q->where('status', ReportStatus::Hidden->value));
        $reportsKept = self::countByMonth('content_reports', 'resolved_at', $from, $to, fn (Builder $q) => $q->where('status', ReportStatus::Kept->value));

        $months = [];

        for ($month = $now->startOfMonth(); $month >= $from; $month = $month->subMonth()) {
            $key = $month->format('Y-m');
            $hidden = $reportsHidden[$key] ?? 0;
            $kept = $reportsKept[$key] ?? 0;

            $months[] = [
                'month' => $key,
                'reviews' => self::monthOf($reviews, $key),
                'forum' => self::monthOf($forum, $key),
                'reports' => [
                    'received' => $reportsReceived[$key] ?? 0,
                    'resolved' => $hidden + $kept,
                    'removed' => $hidden,
                    'kept' => $kept,
                ],
            ];
        }

        return [
            'generated_at' => $now->toIso8601String(),
            'months' => $months,
        ];
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, array<string, mixed>>
     */
    private static function contentStats(array $tables, string $pending, string $rejected, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $stats = ['received' => [], 'published' => [], 'rejected' => [], 'removed' => [], 'moderation_seconds' => [], 'moderated' => []];

        foreach ($tables as $table) {
            self::addInto($stats['received'], self::countByMonth($table, 'created_at', $from, $to));
            self::addInto($stats['published'], self::countByMonth($table, 'published_at', $from, $to));
            self::addInto($stats['rejected'], self::countByMonth($table, 'moderated_at', $from, $to, fn (Builder $q) => $q
                ->where('status', $rejected)
                ->whereNull('removed_at')));

            $month = self::monthExpression('removed_at');
            $removed = DB::table($table)
                ->where('removed_at', '>=', $from)
                ->where('removed_at', '<', $to)
                ->whereNotNull('removal_category')
                ->selectRaw("{$month} as month, removal_category, count(*) as aggregate")
                ->groupByRaw("{$month}, removal_category")
                ->get();

            foreach ($removed as $row) {
                $stats['removed'][$row->month][$row->removal_category] = ($stats['removed'][$row->month][$row->removal_category] ?? 0) + (int) $row->aggregate;
            }

            // The first decision: approval (published_at, kept when published
            // content is later removed) or refusal before publication.
            $decided = 'coalesce(published_at, moderated_at)';
            $month = self::monthExpression($decided);
            $seconds = self::secondsBetween($decided, 'created_at');
            $timing = DB::table($table)
                ->where('status', '!=', $pending)
                ->whereNotNull('moderated_by_id')
                ->where(fn (Builder $q) => $q->whereNull('removed_at')->orWhereNotNull('published_at'))
                ->whereRaw("{$decided} >= ? and {$decided} < ?", [$from, $to])
                ->selectRaw("{$month} as month, count(*) as aggregate, sum({$seconds}) as seconds")
                ->groupByRaw($month)
                ->get();

            foreach ($timing as $row) {
                $stats['moderated'][$row->month] = ($stats['moderated'][$row->month] ?? 0) + (int) $row->aggregate;
                $stats['moderation_seconds'][$row->month] = ($stats['moderation_seconds'][$row->month] ?? 0) + max(0, (float) $row->seconds);
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private static function monthOf(array $stats, string $key): array
    {
        $byCategory = [];

        foreach (RemovalCategory::cases() as $category) {
            $byCategory[$category->value] = (int) ($stats['removed'][$key][$category->value] ?? 0);
        }

        $moderated = (int) ($stats['moderated'][$key] ?? 0);

        return [
            'received' => (int) ($stats['received'][$key] ?? 0),
            'published' => (int) ($stats['published'][$key] ?? 0),
            'rejected' => (int) ($stats['rejected'][$key] ?? 0),
            'removed' => array_sum($byCategory),
            'removed_by_category' => $byCategory,
            // How many decisions the average covers, so totals can weight it.
            'moderated' => $moderated,
            'average_moderation_hours' => $moderated > 0
                ? round($stats['moderation_seconds'][$key] / $moderated / 3600, 1)
                : null,
        ];
    }

    /**
     * @param  (Closure(Builder): mixed)|null  $scope
     * @return array<string, int>
     */
    private static function countByMonth(string $table, string $column, CarbonImmutable $from, CarbonImmutable $to, ?Closure $scope = null): array
    {
        $month = self::monthExpression($column);
        $query = DB::table($table)
            ->where($column, '>=', $from)
            ->where($column, '<', $to);

        if ($scope !== null) {
            $scope($query);
        }

        return $query
            ->selectRaw("{$month} as month, count(*) as aggregate")
            ->groupByRaw($month)
            ->pluck('aggregate', 'month')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @param  array<string, int>  $into
     * @param  array<string, int>  $add
     */
    private static function addInto(array &$into, array $add): void
    {
        foreach ($add as $month => $count) {
            $into[$month] = ($into[$month] ?? 0) + $count;
        }
    }

    private static function monthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? "to_char({$column}, 'YYYY-MM')"
            : "strftime('%Y-%m', {$column})";
    }

    private static function secondsBetween(string $later, string $earlier): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? "extract(epoch from ({$later} - {$earlier}))"
            : "(julianday({$later}) - julianday({$earlier})) * 86400";
    }
}
