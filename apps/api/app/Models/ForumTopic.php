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
use Laravel\Scout\Searchable;

class ForumTopic extends Model
{
    /** @use HasFactory<ForumTopicFactory> */
    use HasFactory, ModeratesForumContent, Searchable;

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

    public function shouldBeSearchable(): bool
    {
        return $this->status === ForumContentStatus::Approved;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing('category');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body,
            'category_slug' => $this->category->slug,
            'category_name' => $this->category->name,
        ];
    }

    public function searchableAs(): string
    {
        return 'forum_topics';
    }

    public function excerpt(int $length = 160): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $this->body)) ?? '');

        if ($plain === '') {
            return '';
        }

        if (mb_strlen($plain) <= $length) {
            return $plain;
        }

        return mb_substr($plain, 0, $length - 1).'…';
    }
}
