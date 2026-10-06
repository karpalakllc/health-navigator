<?php

namespace App\Models;

use App\Enums\FacilityType;
use App\Enums\RemovalCategory;
use App\Enums\ReviewResponseSource;
use App\Enums\ReviewResponseStatus;
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
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, InvalidatesTaxonomyCache, LogsActivity;

    /**
     * Audit log: moderation decisions and every change to the reply under the
     * review. Creation is not logged, and neither the review text nor the
     * author is copied into the log (both may describe someone's health).
     *
     * @var list<string>
     */
    protected static array $recordEvents = ['updated', 'deleted'];

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
     * How many times a member may edit and resend a review refused before
     * publication. A refusal after that is final; a review removed after
     * publication is never resent (its placeholder stays).
     */
    public const MAX_RESUBMISSIONS = 1;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('reviews')
            ->logOnly([
                'status',
                'moderated_by_id',
                'rejection_note',
                'resubmission_count',
                'response_body',
                'response_by_id',
                'response_source',
                'response_status',
                'response_moderated_by_id',
                'response_rejection_note',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
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
            'resubmission_count' => 'integer',
            'resubmitted_at' => 'datetime',
            'response_source' => ReviewResponseSource::class,
            'response_status' => ReviewResponseStatus::class,
            'response_moderated_at' => 'datetime',
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

    /**
     * Refused before it was ever published, and not yet resent the one time
     * allowed: its author may edit it and send it to moderation again.
     */
    public function canBeResubmitted(): bool
    {
        return $this->status === ReviewStatus::Rejected
            && $this->removed_at === null
            && $this->published_at === null
            && (int) $this->resubmission_count < self::MAX_RESUBMISSIONS;
    }

    /**
     * Send a refused review to moderation again with the member's edits. The
     * previous decision's note and moderator are cleared (the audit log keeps
     * them); the aspect ratings are replaced by the new ones.
     *
     * @param  array<string, int>  $aspects
     */
    public function resubmit(int $rating, ?string $body, array $aspects): void
    {
        $this->forceFill([
            'rating' => $rating,
            'body' => $body,
            'status' => ReviewStatus::Pending,
            'rejection_note' => null,
            'moderated_by_id' => null,
            'moderated_at' => null,
            'resubmission_count' => (int) $this->resubmission_count + 1,
            'resubmitted_at' => now(),
        ])->save();

        $this->aspectRatings()->delete();

        foreach ($aspects as $aspect => $value) {
            $this->aspectRatings()->create(['aspect' => $aspect, 'rating' => $value]);
        }
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
     * Public at once. Staff text is a staff response, also when it edits a
     * doctor's own reply.
     */
    public function respond(User $staff, string $body): void
    {
        $this->forceFill([
            'response_body' => self::plainResponse($body),
            'response_by_id' => $staff->getKey(),
            'response_at' => now(),
            'response_source' => ReviewResponseSource::Staff,
            'response_status' => ReviewResponseStatus::Approved,
            'response_moderated_by_id' => $staff->getKey(),
            'response_moderated_at' => now(),
            'response_rejection_note' => null,
        ])->save();
    }

    /**
     * The linked doctor's own reply (DoctorDashboardController). One per
     * review: writing again replaces it. It waits for staff approval while
     * doctor_replies_require_moderation is on, and an edit of an approved
     * reply waits again, so unreviewed text is never public.
     */
    public function replyAsDoctor(User $doctorAccount, string $body, bool $requiresModeration): void
    {
        $this->forceFill([
            'response_body' => self::plainResponse($body),
            'response_by_id' => $doctorAccount->getKey(),
            'response_at' => now(),
            'response_source' => ReviewResponseSource::Doctor,
            'response_status' => $requiresModeration ? ReviewResponseStatus::Pending : ReviewResponseStatus::Approved,
            'response_moderated_by_id' => null,
            'response_moderated_at' => null,
            'response_rejection_note' => null,
        ])->save();
    }

    public function approveDoctorReply(User $staff): void
    {
        $this->forceFill([
            'response_status' => ReviewResponseStatus::Approved,
            'response_moderated_by_id' => $staff->getKey(),
            'response_moderated_at' => now(),
            'response_rejection_note' => null,
        ])->save();
    }

    public function rejectDoctorReply(User $staff, string $note): void
    {
        $this->forceFill([
            'response_status' => ReviewResponseStatus::Rejected,
            'response_moderated_by_id' => $staff->getKey(),
            'response_moderated_at' => now(),
            'response_rejection_note' => $note,
        ])->save();
    }

    public function removeResponse(): void
    {
        $this->forceFill([
            'response_body' => null,
            'response_by_id' => null,
            'response_at' => null,
            'response_source' => null,
            'response_status' => null,
            'response_moderated_by_id' => null,
            'response_moderated_at' => null,
            'response_rejection_note' => null,
        ])->save();
    }

    /** A reply exists, in any state (staff tools act on it). */
    public function hasResponse(): bool
    {
        return filled($this->response_body);
    }

    /** A reply the public may see: only once approved. */
    public function hasPublicResponse(): bool
    {
        return $this->hasResponse() && $this->response_status === ReviewResponseStatus::Approved;
    }

    public function hasDoctorReply(): bool
    {
        return $this->hasResponse() && $this->response_source === ReviewResponseSource::Doctor;
    }

    public function hasPendingDoctorReply(): bool
    {
        return $this->hasDoctorReply() && $this->response_status === ReviewResponseStatus::Pending;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responseModeratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'response_moderated_by_id');
    }

    /**
     * Doctor replies waiting for staff (the „Doctor replies“ queue).
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeWithPendingDoctorReply(Builder $query): Builder
    {
        return $query->whereNotNull('response_body')
            ->where('response_source', ReviewResponseSource::Doctor)
            ->where('response_status', ReviewResponseStatus::Pending);
    }

    public static function plainResponse(string $body): string
    {
        $text = strip_tags(str_replace(["\r\n", "\r"], "\n", $body));
        $text = preg_replace('/[ \t]+\n/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
