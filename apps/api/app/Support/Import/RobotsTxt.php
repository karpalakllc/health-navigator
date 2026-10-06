<?php

namespace App\Support\Import;

/**
 * Minimal robots.txt reading: the Disallow prefixes of the group for our
 * product token, or of "*" when no group names us. Allow lines and
 * wildcards are not interpreted (we only ever ask for a couple of fixed
 * files, so the conservative reading is enough).
 */
final class RobotsTxt
{
    /**
     * @return list<string>
     */
    public static function disallowedFor(string $robots, string $userAgent): array
    {
        $token = mb_strtolower((string) strtok($userAgent, '/ '), 'UTF-8');
        $groups = [];
        $current = [];
        $collectingAgents = false;

        foreach (preg_split('/\r\n|\r|\n/', $robots) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*/', '', $line));

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (! $collectingAgents) {
                    $current = [];
                }

                $current[] = mb_strtolower($value, 'UTF-8');
                $collectingAgents = true;

                continue;
            }

            $collectingAgents = false;

            if ($field === 'disallow') {
                foreach ($current as $agent) {
                    $groups[$agent][] = $value;
                }
            }
        }

        $rules = $groups[$token] ?? $groups['*'] ?? [];

        return array_values(array_filter($rules, fn (string $rule): bool => $rule !== ''));
    }
}
