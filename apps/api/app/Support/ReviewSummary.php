<?php

namespace App\Support;

use App\Models\Doctor;
use App\Models\Facility;

class ReviewSummary
{
    /**
     * @return array{count: int, average_rating: float|null}
     */
    public static function for(Doctor|Facility $reviewable): array
    {
        $stats = $reviewable->reviews()
            ->approved()
            ->selectRaw('COUNT(*) as review_count, AVG(rating) as average_rating')
            ->first();

        $count = (int) ($stats->review_count ?? 0);
        $average = $stats->average_rating !== null
            ? round((float) $stats->average_rating, 1)
            : null;

        return [
            'count' => $count,
            'average_rating' => $count > 0 ? $average : null,
        ];
    }
}
