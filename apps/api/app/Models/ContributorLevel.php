<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's contributor levels, derived from their public content
 * (App\Support\Levels\ContributorLevels; docs/levels.md). Never edited by
 * hand: recomputing overwrites every column.
 *
 * @property int $user_id
 * @property int $review_points
 * @property int $reviews_count
 * @property int $review_helpful_count
 * @property int $review_level
 * @property int $forum_points
 * @property int $forum_topics_count
 * @property int $forum_replies_count
 * @property int $forum_helpful_count
 * @property int $forum_level
 */
class ContributorLevel extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'review_points' => 'integer',
            'reviews_count' => 'integer',
            'review_helpful_count' => 'integer',
            'review_level' => 'integer',
            'forum_points' => 'integer',
            'forum_topics_count' => 'integer',
            'forum_replies_count' => 'integer',
            'forum_helpful_count' => 'integer',
            'forum_level' => 'integer',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
