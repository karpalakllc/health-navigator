<?php

namespace App\Models;

use App\Enums\FacilityType;
use App\Enums\RemovalCategory;
use App\Enums\ReviewStatus;
use App\Models\Concerns\InvalidatesTaxonomyCache;
use App\Support\ReviewAggregates;
use App\Support\ReviewBurstDetector;
use App\Support\TaxonomyCache;
use App\Support\UgcMailer;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'removed_at',
        'removal_category',
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

        static::created(fn (Review $review) => ReviewBurstDetector::check($review));
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
            'removed_at' => 'datetime',
            'removal_category' => RemovalCategory::class,
            'burst_flagged_at' => 'datetime',
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
     * @return HasMany<ReviewAspectRating, $this>
     */
    public function aspectRatings(): HasMany
    {
        return $this->hasMany(ReviewAspectRating::class);
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

    /**
     * What a profile's public review list shows: published reviews, plus the
     * placeholder of every review that was published and later removed.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeInPublicList(Builder $query): Builder
    {
        return $query->where(fn (Builder $list) => $list
            ->where('status', ReviewStatus::Approved)
            ->orWhere(fn (Builder $removed) => $removed
                ->where('status', ReviewStatus::Rejected)
                ->whereNotNull('removed_at')));
    }

    /** Published once, then taken down: shown publicly as a placeholder only. */
    public function isRemoved(): bool
    {
        return $this->status === ReviewStatus::Rejected && $this->removed_at !== null;
    }

    /**
     * Reviews of a profile the public can open: a published doctor or
     * facility, and a pharmacy only while the pharmacies module is on.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeOnPublicProfile(Builder $query): Builder
    {
        $facilityTypes = FacilityType::clinicalValues();

        if (SiteSetting::current()->public_pharmacies) {
            $facilityTypes[] = FacilityType::Pharmacy->value;
        }

        return $query->whereHasMorph(
            'reviewable',
            [Doctor::class, Facility::class],
            function (Builder $profile, string $type) use ($facilityTypes): void {
                $profile->where('is_published', true);

                if ($type === Facility::class) {
                    $profile->whereIn('type', $facilityTypes);
                }
            },
        );
    }

    public function approve(User $moderator): void
    {
        $this->update([
            'status' => ReviewStatus::Approved,
            'published_at' => now(),
            'moderated_by_id' => $moderator->id,
            'moderated_at' => now(),
            'rejection_note' => null,
            'removed_at' => null,
            'removal_category' => null,
        ]);

        UgcMailer::notifyApproved($this->fresh());
    }

    /**
     * Refuse a pending review, or take down a published one.
     *
     * Taking down a published review leaves a public trace: removed_at and a
     * public category, shown in the profile's list as a placeholder (never
     * the note, text or author). published_at is kept so the placeholder
     * stays where the review was. A review refused before it was ever
     * published leaves no trace.
     *
     * @param  bool  $afterReport  taken down through the report queue: the author is told it was removed
     * @param  RemovalCategory|null  $category  the public reason when a published review is taken down (default „other“)
     */
    public function reject(User $moderator, ?string $note = null, bool $afterReport = false, ?RemovalCategory $category = null): void
    {
        $wasPublished = $this->status === ReviewStatus::Approved;

        $this->update([
            'status' => ReviewStatus::Rejected,
            'published_at' => $wasPublished ? $this->published_at : null,
            'moderated_by_id' => $moderator->id,
            'moderated_at' => now(),
            'rejection_note' => $note,
            'removed_at' => $wasPublished ? now() : $this->removed_at,
            'removal_category' => $wasPublished ? ($category ?? RemovalCategory::Other) : $this->removal_category,
        ]);

        UgcMailer::notifyRejected($this->fresh(), removed: $afterReport);
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
