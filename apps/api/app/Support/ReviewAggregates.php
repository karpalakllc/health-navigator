<?php

namespace App\Support;

use App\Enums\ReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * Keeps doctors/facilities.reviews_count and rating_avg in step with their
 * approved reviews (pharmacies are facilities).
 *
 * Always recomputed from the reviews table with one aggregate query, never
 * incremented: an increment drifts the moment a write is lost or replayed,
 * and a status change has three directions (approve, reject, un-approve).
 *
 * rating_avg is the approved average TRUNCATED to two decimals, computed in
 * integer arithmetic. Truncation keeps ReviewSummary's one-decimal rounding
 * identical to rounding the exact average (x.x5 is itself a two-decimal value,
 * so avg >= x.x5 iff truncate2(avg) >= x.x5) — rounding to two decimals first
 * would turn 100/29 = 3.448 into 3.45 and then 3.5. Unrated rows hold 0, so a
 * plain DESC sort puts them last.
 */
final class ReviewAggregates
{
    /** @var array<class-string, string> */
    public const TABLES = [
        Doctor::class => 'doctors',
        Facility::class => 'facilities',
    ];

    public static function recompute(string $reviewableType, int|string|null $reviewableId): void
    {
        $table = self::TABLES[$reviewableType] ?? null;

        if ($table === null || $reviewableId === null) {
            return;
        }

        $stats = DB::table('reviews')
            ->where('reviewable_type', $reviewableType)
            ->where('reviewable_id', $reviewableId)
            ->where('status', ReviewStatus::Approved->value)
            ->selectRaw('COUNT(*) as review_count, COALESCE(SUM(rating), 0) as rating_sum')
            ->first();

        $count = (int) ($stats->review_count ?? 0);
        $sum = (int) ($stats->rating_sum ?? 0);

        DB::table($table)->where('id', $reviewableId)->update([
            'reviews_count' => $count,
            'rating_avg' => self::truncatedAverage($sum, $count),
        ]);
    }

    public static function recomputeFor(Review $review): void
    {
        self::recompute($review->reviewable_type, $review->reviewable_id);
    }

    /**
     * Set-based backfill of every row — for the migration and bulk seeders,
     * which write reviews without model events.
     */
    public static function recomputeAll(): void
    {
        foreach (self::TABLES as $type => $table) {
            DB::update(self::backfillSql($table), [$type, ReviewStatus::Approved->value, $type, ReviewStatus::Approved->value]);
        }
    }

    /**
     * Integer division in both PostgreSQL and SQLite (SUM and COUNT of integers
     * are integers), so the truncation is exact rather than float-dependent.
     */
    public static function backfillSql(string $table): string
    {
        $approved = 'reviews.reviewable_id = '.$table.'.id and reviews.reviewable_type = ? and reviews.status = ?';

        return "update {$table} set "
            ."reviews_count = (select count(*) from reviews where {$approved}), "
            ."rating_avg = coalesce((select (sum(rating) * 100 / count(*)) / 100.0 from reviews where {$approved}), 0)";
    }

    public static function truncatedAverage(int $sum, int $count): string
    {
        if ($count === 0) {
            return '0.00';
        }

        $hundredths = intdiv($sum * 100, $count);

        return sprintf('%d.%02d', intdiv($hundredths, 100), $hundredths % 100);
    }
}
