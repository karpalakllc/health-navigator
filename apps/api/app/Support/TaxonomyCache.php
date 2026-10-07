<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Server-side cache for the small, rarely-changing public taxonomy payloads
 * (specialties, departments, forum categories, doctor languages).
 *
 * Each group is versioned rather than keyed one-by-one, so a single bump
 * invalidates the list and every per-slug entry at once without tracking keys.
 * Bumped from model events (InvalidatesTaxonomyCache) — including on the
 * models behind the embedded counts, so doctors_count / topics_count do not
 * outlive the change that altered them. Pivot-only edits that save no model
 * (attaching a specialty without saving the doctor) are bounded by the TTL.
 */
final class TaxonomyCache
{
    public const TTL_SECONDS = 600;

    public const SPECIALTIES = 'specialties';

    public const DEPARTMENTS = 'departments';

    public const FORUM_CATEGORIES = 'forum-categories';

    /** GET /home/highlights: top specialties, cities and the latest approved reviews. */
    public const HOME_HIGHLIGHTS = 'home-highlights';

    public const LANGUAGES = 'languages';

    /** GET /forum/topics/unanswered: „Прашања без одговор“. */
    public const FORUM_UNANSWERED = 'forum-unanswered';

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public static function remember(string $group, string $key, Closure $resolve, int $ttl = self::TTL_SECONDS): mixed
    {
        return Cache::remember(
            'taxonomy:'.$group.':v'.self::version($group).':'.$key,
            $ttl,
            $resolve,
        );
    }

    public static function flush(string ...$groups): void
    {
        foreach ($groups as $group) {
            // A fresh random version, not an increment: it cannot collide with
            // a version another process cached before an eviction.
            Cache::forever(self::versionKey($group), bin2hex(random_bytes(8)));
        }
    }

    private static function version(string $group): string
    {
        return (string) Cache::rememberForever(self::versionKey($group), fn () => bin2hex(random_bytes(8)));
    }

    private static function versionKey(string $group): string
    {
        return 'taxonomy:'.$group.':version';
    }
}
