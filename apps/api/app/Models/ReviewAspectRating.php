<?php

namespace App\Models;

use App\Enums\ReviewAspect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One optional 1–5 sub-rating of a review („Комуникација“, „Чистота“…).
 * Written with the review; the profile's per-aspect averages live in
 * doctors/facilities.aspect_ratings (ReviewAggregates).
 *
 * @property ReviewAspect $aspect
 * @property int $rating
 */
class ReviewAspectRating extends Model
{
    protected $fillable = [
        'review_id',
        'aspect',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'aspect' => ReviewAspect::class,
            'rating' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
