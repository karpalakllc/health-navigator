<?php

namespace App\Models;

use App\Support\Forum\ForumTagNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A forum keyword. See the forum_tags migration and ForumTagNormalizer.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $match_key
 * @property string $latin
 */
class ForumTag extends Model
{
    /** Tag pages are indexable from this many visible topics (docs/seo.md). */
    public const INDEXABLE_MIN_TOPICS = 3;

    protected $fillable = [
        'name',
        'slug',
        'match_key',
        'latin',
    ];

    /**
     * @return BelongsToMany<ForumTopic, $this>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(ForumTopic::class, 'forum_tag_topic')
            ->withPivot('confirmed')
            ->withTimestamps();
    }

    /**
     * The tag a (normalised) name resolves to, created on first use. Two
     * spellings of the same words share one row through match_key.
     */
    public static function resolve(string $name): self
    {
        $key = ForumTagNormalizer::matchKey($name);

        $existing = self::query()->where('match_key', $key)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            // In a savepoint, so a lost race does not abort a surrounding
            // PostgreSQL transaction.
            return DB::transaction(fn (): self => self::query()->create([
                'name' => $name,
                'match_key' => $key,
                'latin' => ForumTagNormalizer::latin($name),
                'slug' => self::freeSlug(ForumTagNormalizer::slug($name)),
            ]));
        } catch (UniqueConstraintViolationException $e) {
            // Two topics created the same new keyword at once: use theirs.
            return self::query()->where('match_key', $key)->first() ?? throw $e;
        }
    }

    /**
     * Different keys can still slug alike ("lj" vs "l j"); suffix -2, -3 ….
     */
    private static function freeSlug(string $base): string
    {
        $slug = $base;

        for ($suffix = 2; self::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * Adds visible_topics_count and last_activity_at (the latest reply or
     * publication among its publicly visible topics).
     *
     * @param  Builder<ForumTag>  $query
     * @return Builder<ForumTag>
     */
    public function scopeWithVisibleTopicStats(Builder $query): Builder
    {
        return $query
            ->withCount(['topics as visible_topics_count' => fn ($topics) => $topics->visible()])
            ->withMax(['topics as last_activity_at' => fn ($topics) => $topics->visible()], 'last_post_at');
    }
}
