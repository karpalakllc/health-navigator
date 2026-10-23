<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * „Следи ја темата“: the member is told about new published replies in the
 * topic (App\Support\Forum\ForumReplyNotifications).
 *
 * @property int $user_id
 * @property int $forum_topic_id
 */
class ForumTopicSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'forum_topic_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ForumTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id');
    }

    /** Idempotent: following twice keeps the one row. */
    public static function follow(User $user, ForumTopic $topic): void
    {
        self::query()->firstOrCreate([
            'user_id' => $user->getKey(),
            'forum_topic_id' => $topic->getKey(),
        ]);
    }

    public static function unfollow(User $user, ForumTopic $topic): void
    {
        self::query()
            ->where('user_id', $user->getKey())
            ->where('forum_topic_id', $topic->getKey())
            ->delete();
    }

    public static function isFollowing(User $user, ForumTopic $topic): bool
    {
        return self::query()
            ->where('user_id', $user->getKey())
            ->where('forum_topic_id', $topic->getKey())
            ->exists();
    }
}
