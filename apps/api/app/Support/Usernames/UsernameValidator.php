<?php

namespace App\Support\Usernames;

use App\Models\User;
use App\Models\UsernameHistory;
use Closure;

/**
 * The one place that decides whether a username may be taken: registration,
 * the account page, the availability check and the admin panel all ask here.
 *
 * Rules: 3–30 characters; letters of the Latin alphabet (with the Albanian
 * ç ë and the South Slavic č ć đ š ž) or of the Macedonian Cyrillic alphabet
 * — not both in one name —, digits and . _ -; starts with a letter; no two
 * separators in a row. Then the blocked and reserved lists
 * (UsernameTermMatcher) and uniqueness on the folded forms (UsernameNormalizer),
 * including names released in the last six months (UsernameHistory).
 *
 * Messages never say which list matched: a list a member can probe is a list
 * a member can route around.
 */
final class UsernameValidator
{
    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 30;

    private const LATIN = 'a-zA-ZçÇëËčČćĆđĐšŠžŽ';

    private const CYRILLIC = 'абвгдѓежзѕијклљмнњопрстќуфхцчџшАБВГДЃЕЖЗЅИЈКЛЉМНЊОПРСТЌУФХЦЧЏШ';

    /**
     * Why the username cannot be used, or null when it can. One of: length,
     * alphabet, start, separators, mixed_script, not_allowed, taken.
     *
     * `$owner` is the account it is for (null at registration): its own
     * current and previous names are not "taken" for it, and keeping the
     * current name unchanged is always fine — a name allowed when it was given
     * (or a temporary clen-… one) does not become invalid by a list update
     * while staff edit something else on the account.
     */
    public static function problem(string $username, ?User $owner = null): ?string
    {
        $username = UsernameNormalizer::prepare($username);

        if ($owner !== null && $owner->username !== null && $owner->username === $username) {
            return null;
        }

        return self::formatProblem($username)
            ?? (UsernameTermMatcher::matches($username) ? 'not_allowed' : null)
            ?? (self::isTaken($username, $owner) ? 'taken' : null);
    }

    public static function formatProblem(string $username): ?string
    {
        $length = mb_strlen($username);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return 'length';
        }

        $letters = self::LATIN.self::CYRILLIC;

        if (preg_match('/^['.$letters.'0-9._\-]+$/u', $username) !== 1) {
            return 'alphabet';
        }

        if (preg_match('/^['.$letters.']/u', $username) !== 1) {
            return 'start';
        }

        if (preg_match('/[._\-]{2}/', $username) === 1) {
            return 'separators';
        }

        if (preg_match('/['.self::LATIN.']/u', $username) === 1 && preg_match('/['.self::CYRILLIC.']/u', $username) === 1) {
            return 'mixed_script';
        }

        return null;
    }

    public static function isTaken(string $username, ?User $owner = null): bool
    {
        $key = UsernameNormalizer::key($username);
        $skeleton = UsernameNormalizer::skeleton($username);

        $inUse = User::query()
            ->when($owner !== null, fn ($query) => $query->whereKeyNot($owner->getKey()))
            ->where(fn ($query) => $query
                ->where('username_normalized', $key)
                ->orWhere('username_skeleton', $skeleton))
            ->exists();

        if ($inUse) {
            return true;
        }

        return UsernameHistory::query()
            ->held()
            // A member may take back a name they gave up themselves, but not
            // one staff took away from them.
            ->when($owner !== null, fn ($query) => $query->where(fn ($q) => $q
                ->whereNull('user_id')
                ->orWhere('user_id', '!=', $owner->getKey())
                ->orWhere('reason', '!=', 'changed')))
            ->where(fn ($query) => $query
                ->where('username_normalized', $key)
                ->orWhere('username_skeleton', $skeleton))
            ->exists();
    }

    /**
     * Laravel rules for a request field. Runs after the request has replaced
     * the input with UsernameNormalizer::prepare() of itself.
     *
     * @return array<int, mixed>
     */
    public static function rules(?User $owner = null): array
    {
        return [
            'required',
            'string',
            function (string $attribute, mixed $value, Closure $fail) use ($owner): void {
                $problem = is_string($value) ? self::problem($value, $owner) : 'alphabet';

                if ($problem !== null) {
                    $fail("validation.custom.username.{$problem}")->translate();
                }
            },
        ];
    }
}
