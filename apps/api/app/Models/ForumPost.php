<?php

namespace App\Models;

use App\Enums\ForumContentStatus;
use App\Enums\RemovalCategory;
use App\Models\Concerns\ModeratesForumContent;
use Database\Factories\ForumPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ForumPost extends Model
{
    /** @use HasFactory<ForumPostFactory> */
    use HasFactory, ModeratesForumContent;

    protected $fillable = [
        'forum_topic_id',
        'user_id',
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
            'status' => ForumContentStatus::class,
            'published_at' => 'datetime',
            'moderated_at' => 'datetime',
            'removed_at' => 'datetime',
            'removal_category' => RemovalCategory::class,
        ];
    }

    /** Published once, then taken down: shown in its thread as a placeholder only. */
    public function isRemoved(): bool
    {
        return $this->status === ForumContentStatus::Rejected && $this->removed_at !== null;
    }

    /**
     * A reply becoming visible is what makes a topic "active". This runs for both
     * moderator approval and creation-time approval, so the counters can no longer
     * drift depending on which path the reply took.
     */
    protected function afterApproved(): void
    {
        $topic = $this->topic()->first();

        if ($topic !== null && $topic->status === ForumContentStatus::Approved) {
            $topic->recordApprovedReply();
        }
    }

    /**
     * @return BelongsTo<ForumTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_id');
    }

    /**
     * @return MorphMany<ContentReport, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(ContentReport::class, 'reportable');
    }

    /**
     * @param  Builder<ForumPost>  $query
     * @return Builder<ForumPost>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ForumContentStatus::Approved);
    }

    /**
     * What a thread shows: published replies, plus the placeholder of every
     * reply that was published and later removed.
     *
     * @param  Builder<ForumPost>  $query
     * @return Builder<ForumPost>
     */
    public function scopeInThread(Builder $query): Builder
    {
        return $query->where(fn (Builder $thread) => $thread
            ->where('status', ForumContentStatus::Approved)
            ->orWhere(fn (Builder $removed) => $removed
                ->where('status', ForumContentStatus::Rejected)
                ->whereNotNull('removed_at')));
    }
}
