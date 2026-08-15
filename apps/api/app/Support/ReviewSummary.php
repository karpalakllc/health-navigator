<?php

namespace App\Support;

use App\Models\Doctor;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Builder;

class ReviewSummary
{
    public const COUNT_ALIAS = 'approved_reviews_count';

    public const AVG_ALIAS = 'approved_reviews_avg_rating';

    /**
     * Load the approved-review aggregates alongside the parent query.
     *
     * Without this, ReviewSummary::for() runs its own COUNT/AVG per serialized
     * model — 15 extra queries on a doctors page, ~15 more across the verticals
     * of a unified search. withCount/withAvg emit correlated subqueries that are
     * morph-aware, so the polymorphic reviewable_type is still constrained.
     *
     * @template TModel of Doctor|Facility
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function eagerLoad(Builder $query): Builder
    {
        return $query
            ->withCount(['reviews as '.self::COUNT_ALIAS => fn ($relation) => $relation->approved()])
            ->withAvg(['reviews as '.self::AVG_ALIAS => fn ($relation) => $relation->approved()], 'rating');
    }

    /**
     * @return array{count: int, average_rating: float|null}
     */
    public static function for(Doctor|Facility $reviewable): array
    {
        $attributes = $reviewable->getAttributes();

        if (array_key_exists(self::COUNT_ALIAS, $attributes)) {
            $count = (int) $attributes[self::COUNT_ALIAS];
            $average = $attributes[self::AVG_ALIAS] ?? null;
        } else {
            // Fallback for any caller that has not opted into eagerLoad(), so a
            // missed query site degrades to the old cost rather than to wrong data.
            $stats = $reviewable->reviews()
                ->approved()
                ->selectRaw('COUNT(*) as review_count, AVG(rating) as average_rating')
                ->first();

            $count = (int) ($stats->review_count ?? 0);
            $average = $stats->average_rating ?? null;
        }

        return [
            'count' => $count,
            // Postgres returns AVG(int) as a numeric string; cast before rounding
            // so the JSON shape matches what SQLite produced.
            'average_rating' => $count > 0 && $average !== null
                ? round((float) $average, 1)
                : null,
        ];
    }
}
