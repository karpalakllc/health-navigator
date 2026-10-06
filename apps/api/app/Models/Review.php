<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Models\Concerns\InvalidatesTaxonomyCache;
use App\Support\ReviewAggregates;
use App\Support\TaxonomyCache;
use App\Support\UgcMailer;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, InvalidatesTaxonomyCache;

    protected $fillable = [
        'user_id',
        'reviewable_type',
        'reviewable_id',
        'rating',
        'body',
        'status',
        'published_at',
        'moderated_by_id',
        'moderated_at',
        'rejection_note',
    ];

    /**
     * Whether the signed-in viewer marked this review „Корисно“. Not a column:
     * set per request by the review list (ReviewHelpfulVotes::votedBy), null
     * when nobody resolved it.
     */
    public ?bool $viewerHasVotedHelpful = null;

    /** Longest official response staff can attach (characters). */
    public const RESPONSE_MAX_LENGTH = 2000;

    /**
     * Every path that changes what counts as an approved review — approve(),
     * reject(), the Filament bulk actions (which call those), an edit, a
     * delete — goes through a model save or delete, so this is the one place
     * the denormalised doctors/facilities aggregates are refreshed.
     */
    protected static function booted(): void
    {
        static::saved(function (Review $review): void {
            // wasRecentlyCreated stays true for the instance's lifetime, so it
            // is OR-ed with the change check rather than replacing it.
            $affectsAggregate = $review->wasChanged(['status', 'rating', 'reviewable_type', 'reviewable_id'])
                || ($review->wasRecentlyCreated && $review->status === ReviewStatus::Approved);

            if (! $affectsAggregate) {
                return;
            }

            ReviewAggregates::recomputeFor($review);

            if ($review->wasChanged(['reviewable_type', 'reviewable_id'])) {
                ReviewAggregates::recompute($review->getOriginal('reviewable_type'), $review->getOriginal('reviewable_id'));
            }
        });

        static::deleted(fn (Review $review) => ReviewAggregates::recomputeFor($review));
    }

    /**
     * GET /home/highlights lists the latest approved reviews: approving,
     * rejecting, editing or deleting one must not wait out the cache.
     *
     * @return list<string>
     */
    public static function taxonomyCacheGroups(): array
    {
        return [TaxonomyCache::HOME_HIGHLIGHTS];
    }

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'helpful_count' => 'integer',
            'status' => ReviewStatus::class,
            'published_at' => 'datetime',
            'moderated_at' => 'datetime',
            'response_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responseBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'response_by_id');
    }

    /**
     * @return MorphMany<ContentReport, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(ContentReport::class, 'reportable');
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Approved);
    }

    public function approve(User $moderator): void
    {
        $this->update([
            'status' => ReviewStatus::Approved,
            'published_at' => now(),
            'moderated_by_id' => $moderator->id,
            'moderated_at' => now(),
            'rejection_note' => null,
        ]);

        UgcMailer::notifyApproved($this->fresh());
    }

    public function reject(User $moderator, ?string $note = null): void
    {
        $this->update([
            'status' => ReviewStatus::Rejected,
            'published_at' => null,
            'moderated_by_id' => $moderator->id,
            'moderated_at' => now(),
            'rejection_note' => $note,
        ]);

        UgcMailer::notifyRejected($this->fresh());
    }

    /**
     * Attach (or replace) the official response of the reviewed doctor or
     * facility, entered by staff on their behalf. Stored as plain text: any
     * markup is stripped, line breaks are kept (at most one blank line).
     */
    public function respond(User $staff, string $body): void
    {
        $this->forceFill([
            'response_body' => self::plainResponse($body),
            'response_by_id' => $staff->getKey(),
            'response_at' => now(),
        ])->save();
    }

    public function removeResponse(): void
    {
        $this->forceFill([
            'response_body' => null,
            'response_by_id' => null,
            'response_at' => null,
        ])->save();
    }

    public function hasResponse(): bool
    {
        return filled($this->response_body);
    }

    public static function plainResponse(string $body): string
    {
        $text = strip_tags(str_replace(["\r\n", "\r"], "\n", $body));
        $text = preg_replace('/[ \t]+\n/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
