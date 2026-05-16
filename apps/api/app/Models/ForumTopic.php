<?php

namespace App\Models;

use App\Enums\ForumContentStatus;
use App\Models\Concerns\ModeratesForumContent;
use App\Support\ScriptInsensitiveSearch;
use Database\Factories\ForumTopicFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumTopic extends Model
{
    /** @use HasFactory<ForumTopicFactory> */
    use HasFactory, ModeratesForumContent;

    protected $fillable = [
        'forum_category_id',
        'user_id',
        'slug',
        'title',
        'body',
        'status',
        'is_locked',
        'is_pinned',
        'replies_count',
        'last_post_at',
        'published_at',
        'moderated_by_id',
        'moderated_at',
        'rejection_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => ForumContentStatus::class,
            'is_locked' => 'boolean',
            'is_pinned' => 'boolean',
            'replies_count' => 'integer',
            'last_post_at' => 'datetime',
            'published_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ForumCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
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
     * @return HasMany<ForumPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class);
    }

    /**
     * @param  Builder<ForumTopic>  $query
     * @return Builder<ForumTopic>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ForumContentStatus::Approved);
    }

    /**
     * @param  Builder<ForumTopic>  $query
     * @return Builder<ForumTopic>
     */
    public function scopeSearchTitle(Builder $query, string $term): Builder
    {
        return ScriptInsensitiveSearch::whereColumnMatches($query, 'title', $term);
    }

    public function recordApprovedReply(): void
    {
        $this->update([
            'replies_count' => $this->replies_count + 1,
            'last_post_at' => now(),
        ]);
    }
}
