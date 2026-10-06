<?php

namespace App\Models;

use App\Enums\ForumContentStatus;
use App\Models\Concerns\InvalidatesTaxonomyCache;
use App\Models\Concerns\ModeratesForumContent;
use App\Support\ScriptInsensitiveSearch;
use App\Support\TaxonomyCache;
use Database\Factories\ForumTopicFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

class ForumTopic extends Model
{
    /** @use HasFactory<ForumTopicFactory> */
    use HasFactory, InvalidatesTaxonomyCache, ModeratesForumContent, Searchable;

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
        'community_rules_accepted_at',
    ];

    /**
     * GET /forum/categories embeds approved-topic counts.
     *
     * @return list<string>
     */
    public static function taxonomyCacheGroups(): array
    {
        return [TaxonomyCache::FORUM_CATEGORIES];
    }

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
            'community_rules_accepted_at' => 'datetime',
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
     * @return MorphMany<ContentReport, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(ContentReport::class, 'reportable');
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
     * Publicly visible: approved AND filed under a published category. Approval
     * alone is not enough — unpublishing a category hides its topic pages, so
     * every cross-category listing and search must hide them too.
     *
     * @param  Builder<ForumTopic>  $query
     * @return Builder<ForumTopic>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->approved()
            ->whereHas('category', fn (Builder $category) => $category->published());
    }

    /**
     * @param  Builder<ForumTopic>  $query
     * @return Builder<ForumTopic>
     */
    public function scopeSearchTitle(Builder $query, string $term): Builder
    {
        return ScriptInsensitiveSearch::whereColumnMatches($query, 'title', $term);
    }

    /**
     * Atomic so concurrent approvals cannot lose an increment. This is a hot path
     * now that auto-approved replies also land here, not just moderator actions.
     */
    public function recordApprovedReply(): void
    {
        $this->increment('replies_count', 1, ['last_post_at' => now()]);
    }

    /**
     * A published reply was taken down (a report hidden it). The floor keeps a
     * counter that drifted earlier from going negative.
     */
    public function recordRemovedReply(): void
    {
        self::query()
            ->whereKey($this->getKey())
            ->where('replies_count', '>', 0)
            ->decrement('replies_count');

        $this->refresh();
    }

    /**
     * A topic with no replies still has activity: its own publication. Seeding
     * last_post_at here keeps the column non-null for every approved topic, which
     * matters because "ORDER BY last_post_at DESC" sorts NULLs *first* on
     * PostgreSQL and last on SQLite — so reply-less topics would otherwise pin
     * themselves to the top of every listing in production and to the bottom in tests.
     */
    protected function markPublicationTimestamps(): void
    {
        $this->published_at ??= now();
        $this->last_post_at ??= $this->published_at;
    }

    public function shouldBeSearchable(): bool
    {
        if ($this->status !== ForumContentStatus::Approved) {
            return false;
        }

        return (bool) $this->currentCategory()?->is_published;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $category = $this->currentCategory();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body,
            'forum_category_id' => $this->forum_category_id,
            'category_slug' => $category?->slug,
            'category_name' => $category?->name,
            // Filterable (config/scout.php) so search can exclude unpublished
            // categories even if the index lags behind a publication change.
            'category_is_published' => (bool) $category?->is_published,
        ];
    }

    /**
     * The category relation as of the current forum_category_id. loadMissing()
     * would keep a relation loaded before the topic was moved, and index the
     * old category's visibility.
     */
    private function currentCategory(): ?ForumCategory
    {
        if ($this->relationLoaded('category') && (int) $this->category?->getKey() !== (int) $this->forum_category_id) {
            $this->unsetRelation('category');
        }

        $this->loadMissing('category');

        return $this->category;
    }

    /**
     * Prefixed like Scout's default, so SCOUT_PREFIX moves the data and the
     * index settings (config/scout.php) to the same index.
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'forum_topics';
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
