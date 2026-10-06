<?php

namespace Database\Seeders\Concerns;

/**
 * Seeded demo and E2E accounts get a fixed username from their address
 * („marija@zdravje360.test“ → „marija“, „reviewer-0@e2e.test“ →
 * „reviewer_0“), so they can post straight away instead of being asked to
 * replace a temporary one, and a reseed gives them the same name again.
 * Seeders bypass UsernameValidator on purpose: these are fixtures.
 */
trait SeedsUsernames
{
    protected static function seededUsername(string $email): string
    {
        $local = strtolower(strstr($email, '@', true) ?: $email);
        $username = trim((string) preg_replace('/[^a-z0-9]+/', '_', $local), '_');

        if ($username === '' || ! ctype_alpha($username[0])) {
            $username = 'u_'.$username;
        }

        return substr($username, 0, 30);
    }
}
