<?php

namespace App\Support\Usernames;

use App\Enums\UsernameMatchType;
use App\Models\UsernameTerm;
use Illuminate\Support\Facades\Cache;

/**
 * Whether a username hits the blocked or reserved lists (UsernameTerm).
 *
 * The username is compared in four forms — what it says (key), what it looks
 * like (skeleton), and both with repeated letters collapsed („fuuuck“) —
 * against each term's key and skeleton:
 *
 * - `exact` terms must equal the whole username or one of its words, so „dr“
 *   refuses „dr.marko“ and „Dr_1“ but not „Dragan“;
 * - `contains` terms may appear anywhere, after the `allowed` exceptions have
 *   been masked out of the username („Scunthorpe“).
 *
 * Collapsing is applied to the username only, never to a term: „boob“ must
 * not turn into „bob“ and refuse Bob.
 */
final class UsernameTermMatcher
{
    private const CACHE_KEY = 'username_terms:v1';

    /** Marks a number-only word or term, which is compared as written. */
    private const DIGITS = 'digits:';

    public static function matches(string $username): bool
    {
        return self::firstMatch($username) !== null;
    }

    /**
     * For staff („Test a username“ in the admin panel): the listed terms that
     * refuse this username, so a false refusal can be traced to its term and
     * answered with an `allowed` exception.
     *
     * @return list<UsernameTerm>
     */
    public static function explain(string $username): array
    {
        $form = self::firstMatch($username);

        if ($form === null) {
            return [];
        }

        $term = str_starts_with($form, self::DIGITS) ? substr($form, strlen(self::DIGITS)) : null;

        return UsernameTerm::query()
            ->active()
            ->where(fn ($query) => $term !== null
                ? $query->where('term', $term)
                : $query->where('term_normalized', $form)->orWhere('term_skeleton', $form))
            ->where('match_type', '!=', UsernameMatchType::Allowed->value)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * The folded term that refuses the username, or null when none does.
     */
    private static function firstMatch(string $username): ?string
    {
        $lists = self::lists();

        $forms = self::forms($username);

        $words = [];
        foreach (UsernameNormalizer::tokens($username) as $token) {
            // A run of digits is a number, not leetspeak: „marko55“ must not
            // read as „marko ss“. It only meets terms that are numbers („420“).
            if (ctype_digit($token)) {
                $words[self::DIGITS.$token] = true;

                continue;
            }

            foreach (self::forms($token) as $form) {
                $words[$form] = true;
            }
        }

        foreach ([...$forms, ...array_keys($words)] as $form) {
            if (isset($lists['exact'][$form])) {
                return $form;
            }
        }

        foreach ($forms as $form) {
            $masked = self::mask($form, $lists['allowed']);

            foreach ($lists['contains'] as $needle) {
                if (str_contains($masked, $needle)) {
                    return $needle;
                }
            }
        }

        return null;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<string>
     */
    private static function forms(string $value): array
    {
        $key = UsernameNormalizer::key($value);
        $skeleton = UsernameNormalizer::skeleton($value);

        return array_values(array_unique(array_filter([
            $key,
            $skeleton,
            UsernameNormalizer::collapse($key),
            UsernameNormalizer::collapse($skeleton),
        ], fn (string $form): bool => $form !== '')));
    }

    /**
     * Replace every allowed word in the form with a gap, longest first, so a
     * `contains` term can no longer be found inside it. The gap is not a
     * letter, so nothing can be spelt across it.
     *
     * @param  list<string>  $allowed
     */
    private static function mask(string $form, array $allowed): string
    {
        foreach ($allowed as $word) {
            if (str_contains($form, $word)) {
                $form = str_replace($word, '#', $form);
            }
        }

        return $form;
    }

    /**
     * The active lists, folded, cached until a term changes.
     *
     * @return array{exact: array<string, true>, contains: list<string>, allowed: list<string>}
     */
    private static function lists(): array
    {
        /** @var array{exact: array<string, true>, contains: list<string>, allowed: list<string>} */
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $exact = [];
            $contains = [];
            $allowed = [];

            UsernameTerm::query()
                ->active()
                ->get(['term', 'term_normalized', 'term_skeleton', 'match_type'])
                ->each(function (UsernameTerm $term) use (&$exact, &$contains, &$allowed): void {
                    if (ctype_digit($term->term)) {
                        if ($term->match_type === UsernameMatchType::Exact) {
                            $exact[self::DIGITS.$term->term] = true;
                        }

                        return;
                    }

                    $forms = array_values(array_unique(array_filter(
                        [$term->term_normalized, $term->term_skeleton],
                        fn (?string $form): bool => $form !== null && $form !== '',
                    )));

                    foreach ($forms as $form) {
                        match ($term->match_type) {
                            UsernameMatchType::Exact => $exact[$form] = true,
                            UsernameMatchType::Contains => $contains[] = $form,
                            // Collapsed too, to excuse the collapsed username forms.
                            UsernameMatchType::Allowed => array_push($allowed, $form, UsernameNormalizer::collapse($form)),
                        };
                    }
                });

            $allowed = array_values(array_unique($allowed));
            usort($allowed, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

            return [
                'exact' => $exact,
                'contains' => array_values(array_unique($contains)),
                'allowed' => $allowed,
            ];
        });
    }
}
