<?php

namespace App\Support\Forum;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\SiteSetting;
use App\Support\FrontendUrl;

/**
 * Profile cards in forum posts: a pasted link to a Zdravje360 doctor,
 * facility or pharmacy profile („https://zdravje360.mk/doctors/ana-…“ or
 * just „/doctors/ana-…“) is shown as a small card under the post. Only links
 * to this site count, only published profiles are shown, at most three per
 * post, and nothing is stored: the body stays plain text.
 */
final class ForumProfileLinks
{
    public const MAX_PER_POST = 3;

    private const PATTERN = '~(https?://[^\s/<>"\']+)?/(doctors|facilities|pharmacies)/([a-z0-9][a-z0-9-]{0,190})~iu';

    /**
     * The profile cards for each body, keyed like the input; resolved in one
     * query per kind for a whole thread page.
     *
     * @param  array<int|string, string>  $bodies
     * @return array<int|string, list<array{kind: string, slug: string, name: string, path: string, subtitle: string|null, city: string|null}>>
     */
    public static function forBodies(array $bodies): array
    {
        $empty = array_map(fn (): array => [], $bodies);

        if (! SiteSetting::current()->forumFeatureOn('forum_profile_cards_enabled')) {
            return $empty;
        }

        $links = array_map(self::extract(...), $bodies);
        $wanted = ['doctors' => [], 'facilities' => [], 'pharmacies' => []];

        foreach ($links as $list) {
            foreach ($list as [$kind, $slug]) {
                $wanted[$kind][$slug] = true;
            }
        }

        $cards = self::resolve($wanted);
        $result = [];

        foreach ($links as $key => $list) {
            $result[$key] = array_values(array_filter(array_map(
                fn (array $link): ?array => $cards[$link[0]][$link[1]] ?? null,
                $list,
            )));
        }

        return $result;
    }

    /**
     * Distinct (kind, slug) pairs in the order they appear, at most three.
     *
     * @return list<array{0: 'doctors'|'facilities'|'pharmacies', 1: string}>
     */
    public static function extract(string $body): array
    {
        if (preg_match_all(self::PATTERN, $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $ownHost = self::normaliseHost((string) parse_url(FrontendUrl::base(), PHP_URL_HOST));
        $found = [];

        foreach ($matches as $match) {
            $origin = $match[1][0];
            $offset = (int) $match[0][1];

            if ($origin !== '') {
                // Someone else's site with the same path is not a profile.
                if (self::normaliseHost((string) parse_url($origin, PHP_URL_HOST)) !== $ownHost) {
                    continue;
                }
            } elseif ($offset > 0 && ! preg_match('/\s|[(\[„"«]/u', mb_substr(substr($body, 0, $offset), -1))) {
                // A bare path must start a word („види /doctors/…“), not sit
                // inside another URL or word.
                continue;
            }

            /** @var 'doctors'|'facilities'|'pharmacies' $kind */
            $kind = strtolower($match[2][0]);
            $slug = strtolower(rtrim($match[3][0], '-'));
            $key = $kind.'/'.$slug;

            if ($slug === '' || isset($found[$key])) {
                continue;
            }

            $found[$key] = [$kind, $slug];

            if (count($found) >= self::MAX_PER_POST) {
                break;
            }
        }

        return array_values($found);
    }

    private static function normaliseHost(string $host): string
    {
        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * @param  array{doctors: array<string, true>, facilities: array<string, true>, pharmacies: array<string, true>}  $wanted
     * @return array<string, array<string, array{kind: string, slug: string, name: string, path: string, subtitle: string|null, city: string|null}>>
     */
    private static function resolve(array $wanted): array
    {
        $cards = ['doctors' => [], 'facilities' => [], 'pharmacies' => []];

        if ($wanted['doctors'] !== []) {
            Doctor::query()
                ->published()
                ->whereIn('slug', array_keys($wanted['doctors']))
                ->with(['specialties' => fn ($relation) => $relation->published()])
                ->get()
                ->each(function (Doctor $doctor) use (&$cards): void {
                    $cards['doctors'][(string) $doctor->slug] = [
                        'kind' => 'doctor',
                        'slug' => (string) $doctor->slug,
                        'name' => (string) $doctor->full_name,
                        'path' => '/doctors/'.$doctor->slug,
                        'subtitle' => $doctor->specialties->first()?->name,
                        'city' => $doctor->city ?: null,
                    ];
                });
        }

        foreach (['facilities' => false, 'pharmacies' => true] as $kind => $pharmacy) {
            if ($wanted[$kind] === []) {
                continue;
            }

            $query = Facility::query()->published()->whereIn('slug', array_keys($wanted[$kind]));
            $pharmacy ? $query->pharmacy() : $query->clinical();

            $query->get()->each(function (Facility $facility) use (&$cards, $kind, $pharmacy): void {
                $cards[$kind][(string) $facility->slug] = [
                    'kind' => $pharmacy ? 'pharmacy' : 'facility',
                    'slug' => (string) $facility->slug,
                    'name' => (string) $facility->name,
                    'path' => '/'.$kind.'/'.$facility->slug,
                    'subtitle' => null,
                    'city' => $facility->city ?: null,
                ];
            });
        }

        return $cards;
    }
}
