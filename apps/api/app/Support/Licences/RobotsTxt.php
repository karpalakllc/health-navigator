<?php

namespace App\Support\Licences;

/**
 * Just enough of robots.txt to honour it: the group for our user agent (or
 * „*“), its Allow / Disallow prefixes, longest match wins, an empty Disallow
 * allows everything. Wildcards are read conservatively: a Disallow pattern
 * blocks everything from its first „*“ or „$“ on, an Allow pattern with one
 * is ignored.
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

        foreach ($rules ?? [] as [$allow, $prefix]) {
            if ($prefix === '') {
                continue;
            }

            if (str_starts_with($path, $prefix) && strlen($prefix) > $longest) {
                $longest = strlen($prefix);
                $verdict = $allow;
            }
        }

        return $verdict;
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

            if ($current === null || ! in_array($field, ['allow', 'disallow'], true)) {
                continue;
            }

            $wildcard = strcspn($value, '*$');

            if ($wildcard < strlen($value)) {
                if ($field === 'allow') {
                    continue;
                }

                $value = substr($value, 0, $wildcard);
                $value = $value === '' ? '/' : $value;
            }

            $groups[$current]['rules'][] = [$field === 'allow', $value];
        }

        return $groups;
    }
}
