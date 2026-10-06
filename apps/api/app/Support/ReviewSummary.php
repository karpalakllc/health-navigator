<?php

namespace App\Support;

use App\Models\Doctor;
use App\Models\Facility;

/**
 * The public review_summary block, read from the denormalised
 * reviews_count / rating_avg columns that ReviewAggregates maintains.
 *
 * Those columns replaced per-row COUNT/AVG correlated subqueries
 * (withCount/withAvg), which every directory list, profile and unified-search
 * hit used to carry.
 */
class ReviewSummary
{
    /**
     * @return array{count: int, average_rating: float|null}
     */
    public static function for(Doctor|Facility $reviewable): array
    {
        $attributes = $reviewable->getAttributes();

        if (array_key_exists('reviews_count', $attributes)) {
            $count = (int) $attributes['reviews_count'];
            $average = $attributes['rating_avg'] ?? null;
        } else {
            // A caller that selected specific columns: degrade to one query
            // rather than to wrong data.
            $stats = $reviewable->reviews()
                ->approved()
                ->selectRaw('COUNT(*) as review_count, AVG(rating) as average_rating')
                ->first();

            $count = (int) ($stats->review_count ?? 0);
            $average = $stats->average_rating ?? null;
        }

        return [
            'count' => $count,
            // rating_avg is truncated to two decimals, which rounds to one
            // decimal exactly as the full average would (ReviewAggregates).
            'average_rating' => $count > 0 && $average !== null
                ? round((float) $average, 1)
                : null,
        ];
    }
}
