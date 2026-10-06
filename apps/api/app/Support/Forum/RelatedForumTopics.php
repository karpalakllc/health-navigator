<?php

namespace App\Support\Forum;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTopic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Internal links into the forum („Слични теми“, and the forum box on doctor
 * and facility profiles). Every result is publicly visible (approved, in a
 * published category); see docs/seo.md for why each rule is as strict as it is.
 */
final class RelatedForumTopics
{
    public const DEFAULT_LIMIT = 5;

    public const MAX_LIMIT = 10;

    /**
     * Topics sharing the most keywords first (then the most recently active),
     * topped up from the same category.
     *
     * @return Collection<int, ForumTopic>
     */
    public static function forTopic(ForumTopic $topic, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $tagIds = $topic->tags()->pluck('forum_tags.id')->all();
        $related = new Collection;

        if ($tagIds !== []) {
            $related = self::base()
                ->whereKeyNot($topic->getKey())
                ->whereHas('tags', fn (Builder $tags) => $tags->whereKey($tagIds))
                ->withCount(['tags as shared_tags_count' => fn (Builder $tags) => $tags->whereKey($tagIds)])
                ->orderByDesc('shared_tags_count')
                ->orderByDesc('last_post_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();
        }

        if ($related->count() < $limit) {
            $related = $related->concat(
                self::base()
                    ->where('forum_category_id', $topic->forum_category_id)
                    ->whereKeyNot([$topic->getKey(), ...$related->modelKeys()])
                    ->orderByDesc('last_post_at')
                    ->orderByDesc('id')
                    ->limit($limit - $related->count())
                    ->get(),
            );
        }

        return $related->values();
    }

    /**
     * Only through keywords a moderator or staff member saved (pivot
     * confirmed) that spell exactly the doctor's name. A name in free text is
     * not enough: two doctors can share one, and linking a profile to a
     * discussion is a statement about that person.
     *
     * @return Collection<int, ForumTopic>
     */
    public static function forDoctor(Doctor $doctor, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $keys = self::doctorNameKeys($doctor->full_name);

        if ($keys === []) {
            return new Collection;
        }

        return self::base()
            ->whereHas('tags', fn (Builder $tags) => $tags
                ->whereIn('match_key', $keys)
                ->where('forum_tag_topic.confirmed', true))
            ->orderByDesc('last_post_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * A keyword spelling the facility's name, or the name in the topic title.
     *
     * @return Collection<int, ForumTopic>
     */
    public static function forFacility(Facility $facility, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $name = trim((string) $facility->name);

        if (mb_strlen($name) < 3) {
            return new Collection;
        }

        $key = self::nameKey($name);

        return self::base()
            ->where(function (Builder $query) use ($key, $name): void {
                $query->where(fn (Builder $title) => $title->searchTitle($name));

                if ($key !== null) {
                    $query->orWhereHas('tags', fn (Builder $tags) => $tags->where('match_key', $key));
                }
            })
            ->orderByDesc('last_post_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Builder<ForumTopic>
     */
    private static function base(): Builder
    {
        return ForumTopic::query()->visible()->with(['user', 'category']);
    }

    /**
     * Profiles store names with a title („д-р Елена Димитрова“); a keyword
     * may carry it or not. The name without leading titles, alone and with
     * the common „д-р“/„dr“ prefixes, are all the same person.
     *
     * @return list<string>
     */
    private static function doctorNameKeys(?string $name): array
    {
        $bare = preg_replace(
            '/^((д-р|др|dr|d-r|проф|prof|доц|doc|м-р|mr|прим|prim|асист|asist)\.?\s+)+/u',
            '',
            ForumTagNormalizer::clean((string) $name),
        ) ?? '';
        $key = self::nameKey($bare);

        // A single word (a surname alone) is too ambiguous to link a person.
        if ($key === null || ! str_contains($key, ' ')) {
            return [];
        }

        return [$key, 'd r '.$key, 'dr '.$key];
    }

    private static function nameKey(?string $name): ?string
    {
        $normalised = ForumTagNormalizer::clean((string) $name);

        // A keyword is at most MAX_LENGTH long, so a longer name cannot match.
        if (mb_strlen($normalised) < ForumTagNormalizer::MIN_LENGTH
            || mb_strlen($normalised) > ForumTagNormalizer::MAX_LENGTH) {
            return null;
        }

        return ForumTagNormalizer::matchKey($normalised);
    }
}
