<?php

namespace App\Models;

use App\Enums\ForumContentStatus;
use App\Models\Concerns\ModeratesForumContent;
use Database\Factories\ForumPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
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
     * @param  Builder<ForumPost>  $query
     * @return Builder<ForumPost>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ForumContentStatus::Approved);
    }
}
