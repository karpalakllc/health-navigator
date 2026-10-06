<?php

namespace App\Support\Usernames;

use App\Models\User;

/**
 * The placeholder username an account gets when it has none of its own yet:
 * existing members when usernames were introduced, and accounts created
 * outside registration (admin panel, seeders) without one. „clen“ („член“,
 * member) is a reserved word, so nobody can choose a name that looks like a
 * placeholder.
 */
final class TemporaryUsername
{
    public const PREFIX = 'clen-';

    /**
     * Lower-case letters without the easily confused i l o, without r and v
     * (the skeleton reads „rn“ as m and „vv“ as w), and only the digits that
     * leetspeak folding leaves alone — so two placeholders that differ as
     * strings also differ in both folded (unique) forms.
     */
    private const ALPHABET = 'abcdefghjkmnpqstuwxyz2689';

    public static function candidate(): string
    {
        $suffix = '';

        for ($i = 0; $i < 6; $i++) {
            $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return self::PREFIX.$suffix;
    }

    /** A candidate no account holds yet. */
    public static function generate(): string
    {
        do {
            $username = self::candidate();
        } while (User::query()->where('username_normalized', UsernameNormalizer::key($username))->exists());

        return $username;
    }

    public static function isTemporary(?string $username): bool
    {
        return $username !== null && str_starts_with($username, self::PREFIX);
    }
}
