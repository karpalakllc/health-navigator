<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Support\UgcMailer;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

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

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'published_at' => 'datetime',
            'moderated_at' => 'datetime',
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
    }
}
