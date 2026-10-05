<?php

namespace App\Support;

use Sentry\Event;
use Sentry\EventHint;

/**
 * Sentry `before_send` hook: strips credentials and contact details from the
 * request the SDK attaches to an event.
 *
 * send_default_pii=false already drops cookies, the client IP and sensitive
 * headers, but the SDK still records the request body, the URL and its query
 * string verbatim — a failed login or password reset would otherwise ship the
 * password, and a verification or reset link its token and signature.
 *
 * A class (not a closure in config/sentry.php) so `php artisan config:cache`
 * can serialise the config.
 */
final class SentryEventScrubber
{
    public const FILTERED = '[Filtered]';

    /** Matches password, password_confirmation, current_password, reset_token, signature, ... */
    private const SENSITIVE_KEY = '/pass(word)?|token|secret|signature|api_?key|authori[sz]ation|^email$|^hash$/i';

    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        $request = $event->getRequest();

        if ($request === []) {
            return $event;
        }

        if (is_array($request['data'] ?? null)) {
            $request['data'] = self::scrubArray($request['data']);
        }

        if (is_string($request['query_string'] ?? null)) {
            $request['query_string'] = self::scrubQuery($request['query_string']);
        }

        if (is_string($request['url'] ?? null)) {
            $request['url'] = self::scrubUrl($request['url']);
        }

        $event->setRequest($request);

        return $event;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY, $key)) {
                $data[$key] = self::FILTERED;
            } elseif (is_array($value)) {
                $data[$key] = self::scrubArray($value);
            }
        }

        return $data;
    }

    private static function scrubQuery(string $query): string
    {
        if ($query === '') {
            return $query;
        }

        parse_str($query, $params);

        return http_build_query(self::scrubArray($params));
    }

    private static function scrubUrl(string $url): string
    {
        $parts = explode('?', $url, 2);

        if (count($parts) === 1) {
            return $url;
        }

        [$path, $rest] = $parts;
        [$query, $fragment] = array_pad(explode('#', $rest, 2), 2, null);

        return $path.'?'.self::scrubQuery($query).($fragment !== null ? '#'.$fragment : '');
    }
}
