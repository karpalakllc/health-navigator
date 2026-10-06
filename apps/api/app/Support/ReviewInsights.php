<?php

namespace App\Support;

use App\Enums\ReviewAspect;
use App\Enums\ReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The extra review blocks of a profile's review list: per-aspect averages
 * (from the denormalised aspect_ratings column) and the rating trend over the
 * last twelve months. Both count approved reviews only — never pending,
 * refused or removed ones.
 */
final class ReviewInsights
{
    /** Fewer approved reviews than this in the twelve months: no trend. */
    public const TREND_MIN_REVIEWS = 5;

    /** Twelve months in four three-month periods. */
    private const TREND_PERIODS = 4;

    private const MONTHS_PER_PERIOD = 3;

    /**
     * Every aspect of the profile's type, in display order, with how many
     * approved reviews rated it. The average is withheld (null) below
     * ReviewAspect::MIN_RATINGS_SHOWN ratings.
     *
     * @return list<array{key: string, count: int, average: float|null}>
     */
    public static function aspects(Doctor|Facility $reviewable): array
    {
        $stored = self::storedAspects($reviewable);
        $result = [];

        foreach (ReviewAspect::forReviewable($reviewable::class) as $aspect) {
            $count = (int) ($stored[$aspect->value]['count'] ?? 0);
            $average = $stored[$aspect->value]['average'] ?? null;

            $result[] = [
                'key' => $aspect->value,
                'count' => $count,
                'average' => $count >= ReviewAspect::MIN_RATINGS_SHOWN && $average !== null
                    ? round((float) $average, 1)
                    : null,
            ];
        }

        return $result;
    }

    /**
     * The average rating per three-month period over the last twelve months
     * (the current calendar month and the eleven before it), oldest first;
     * null when fewer than TREND_MIN_REVIEWS approved reviews were published
     * in that time. A period without reviews has a null average.
     *
     * @return list<array{start: string, end: string, count: int, average: float|null}>|null
     */
    public static function trend(Doctor|Facility $reviewable, ?CarbonImmutable $now = null): ?array
    {
        $now ??= CarbonImmutable::now();
        $first = $now->startOfMonth()->subMonths(self::TREND_PERIODS * self::MONTHS_PER_PERIOD - 1);

        $periods = [];
        $selects = [];
        $bindings = [];

        for ($i = 0; $i < self::TREND_PERIODS; $i++) {
            $start = $first->addMonths($i * self::MONTHS_PER_PERIOD);
            $end = $start->addMonths(self::MONTHS_PER_PERIOD);
            $periods[] = [$start, $end];

            $selects[] = "sum(case when published_at >= ? and published_at < ? then 1 else 0 end) as c{$i}";
            $selects[] = "sum(case when published_at >= ? and published_at < ? then rating else 0 end) as s{$i}";
            array_push($bindings, $start, $end, $start, $end);
        }

        $row = DB::table('reviews')
            ->where('reviewable_type', $reviewable::class)
            ->where('reviewable_id', $reviewable->getKey())
            ->where('status', ReviewStatus::Approved->value)
            ->where('published_at', '>=', $first)
            ->selectRaw(implode(', ', $selects), array_map(
                fn (CarbonImmutable $date): string => $date->format('Y-m-d H:i:s'),
                $bindings,
            ))
            ->first();

        $result = [];
        $total = 0;

        foreach ($periods as $i => [$start, $end]) {
            $count = (int) ($row->{"c{$i}"} ?? 0);
            $sum = (int) ($row->{"s{$i}"} ?? 0);
            $total += $count;

            $result[] = [
                'start' => $start->toDateString(),
                // Inclusive last day, as a person reads a period.
                'end' => $end->subDay()->toDateString(),
                'count' => $count,
                'average' => $count > 0 ? round($sum / $count, 1) : null,
            ];
        }

        return $total >= self::TREND_MIN_REVIEWS ? $result : null;
    }

    /**
     * @return array<string, array{count?: int, average?: float}>
     */
    private static function storedAspects(Doctor|Facility $reviewable): array
    {
        $attributes = $reviewable->getAttributes();

        $raw = array_key_exists('aspect_ratings', $attributes)
            ? $attributes['aspect_ratings']
            // A caller that selected specific columns: one query, not wrong data.
            : DB::table(ReviewAggregates::TABLES[$reviewable::class])->where('id', $reviewable->getKey())->value('aspect_ratings');

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
