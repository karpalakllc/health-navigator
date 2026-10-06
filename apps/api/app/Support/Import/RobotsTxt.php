<?php

namespace App\Support\Import;

/**
 * robots.txt as RFC 9309 reads it, for every source we download from:
 * the group naming our product token (else „*“), Allow / Disallow rules
 * where „*“ matches any run of characters and a trailing „$“ anchors the
 * end; the longest matching rule wins, Allow on a tie; an empty Disallow
 * allows everything. Fetching robots.txt itself (and failing closed on a
 * server error) is the fetcher's job.
 */
final class RobotsTxt
{
    /**
     * @param  string  $agentToken  our product token, e.g. "Zdravje360-DirectoryImport"
     */
    public static function allows(string $robots, string $agentToken, string $path): bool
    {
        $groups = self::groups($robots);
        $token = mb_strtolower($agentToken);
        $rules = null;

        foreach ($groups as $group) {
            foreach ($group['agents'] as $agent) {
                if ($agent !== '*' && $agent !== '' && str_contains($token, $agent)) {
                    $rules = $group['rules'];
                    break 2;
                }
            }
        }

        if ($rules === null) {
            foreach ($groups as $group) {
                if (in_array('*', $group['agents'], true)) {
                    $rules = $group['rules'];
                    break;
                }
            }
        }

        $verdict = true;
        $longest = -1;

        foreach ($rules ?? [] as [$allow, $pattern]) {
            if ($pattern === '' || ! self::matches($pattern, $path)) {
                continue;
            }

            $length = strlen($pattern);

            if ($length > $longest || ($length === $longest && $allow)) {
                $longest = $length;
                $verdict = $allow;
            }
        }

        return $verdict;
    }

    private static function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $body = $anchored ? substr($pattern, 0, -1) : $pattern;
        $regex = implode('.*', array_map(fn (string $part): string => preg_quote($part, '~'), explode('*', $body)));

        return preg_match('~^'.$regex.($anchored ? '$' : '').'~', $path) === 1;
    }

    /**
     * @return list<array{agents: list<string>, rules: list<array{0: bool, 1: string}>}>
     */
    private static function groups(string $robots): array
    {
        $groups = [];
        $current = null;
        $lastWasAgent = false;

        foreach (preg_split('/\R/u', $robots) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*/', '', $line));

            if (! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (! $lastWasAgent || $current === null) {
                    $groups[] = ['agents' => [], 'rules' => []];
                    $current = array_key_last($groups);
                }

                $groups[$current]['agents'][] = strtolower($value);
                $lastWasAgent = true;

                continue;
            }

            $lastWasAgent = false;

            if ($current !== null && in_array($field, ['allow', 'disallow'], true)) {
                $groups[$current]['rules'][] = [$field === 'allow', $value];
            }
        }

        return $groups;
    }
}
