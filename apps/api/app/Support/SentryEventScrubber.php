<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Sentry\Event;
use Sentry\EventHint;
use Throwable;

/**
 * Sentry `before_send` / `before_send_transaction` hooks: strip credentials,
 * contact details and health data from the request the SDK attaches to an
 * event, and from exception messages.
 *
 * send_default_pii=false already drops cookies, the client IP and sensitive
 * headers, but the SDK still records the request body, the URL and its query
 * string verbatim — a failed login or password reset would otherwise ship the
 * password, and a verification or reset link its token and signature. Triage
 * `answers`/`values` are symptoms, so they are health data. Exception messages
 * carry data too: a QueryException interpolates its bindings into the SQL.
 *
 * A class (not a closure in config/sentry.php) so `php artisan config:cache`
 * can serialise the config.
 */
final class SentryEventScrubber
{
    public const FILTERED = '[Filtered]';

    /** Matches password, current_password, reset_token, signature, new_email, triage answers, ... */
    private const SENSITIVE_KEY = '/pass(word)?|token|secret|signature|api_?key|authori[sz]ation|e-?mail|^hash$|^answers$|^values$/i';

    /** Patterns redacted from free text (exception messages, log messages). */
    private const SENSITIVE_TEXT = [
        // Email addresses.
        '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/',
        // bcrypt / argon2 password hashes.
        '/\$2[aby]?\$\d{2}\$[.\/A-Za-z0-9]{53}/',
        '/\$argon2(?:id|i|d)\$[^\s\'"]+/',
        // Long hex / base64(url) runs: reset tokens, signatures, Sanctum tokens.
        // A digit and a letter are required, and "/" is excluded, so file paths
        // and ordinary words survive.
        '/(?<![A-Za-z0-9+_-])(?=[A-Za-z0-9+_-]*\d)(?=[A-Za-z0-9+_-]*[A-Za-z])[A-Za-z0-9+_-]{32,}={0,2}/',
    ];

    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        self::scrubExceptions($event, $hint);

        $message = $event->getMessage();

        if ($message !== null && $message !== '') {
            $event->setMessage(
                self::scrubText($message),
                $event->getMessageParams(),
                $event->getMessageFormatted() !== null ? self::scrubText($event->getMessageFormatted()) : null,
            );
        }

        return self::scrubRequest($event);
    }

    /**
     * Transactions carry the same request (body, URL, query string) as errors.
     */
    public static function beforeSendTransaction(Event $transaction, ?EventHint $hint = null): ?Event
    {
        return self::scrubRequest($transaction);
    }

    private static function scrubExceptions(Event $event, ?EventHint $hint): void
    {
        $exceptions = $event->getExceptions();

        if ($exceptions === []) {
            return;
        }

        $queryExceptions = [];

        for ($throwable = $hint?->exception; $throwable instanceof Throwable; $throwable = $throwable->getPrevious()) {
            if ($throwable instanceof QueryException) {
                $queryExceptions[] = $throwable;
            }
        }

        foreach ($exceptions as $exception) {
            $value = $exception->getValue();
            $placeholders = null;

            foreach ($queryExceptions as $queryException) {
                if ($value === $queryException->getMessage()) {
                    $placeholders = $queryException->getSql();
                }
            }

            // Swap the interpolated SQL for the placeholder version. Without a
            // matching QueryException (no hint, or a re-wrapped message) the
            // SQL cannot be told apart from its bindings, so it is dropped.
            $value = (string) preg_replace_callback(
                '/(\(Connection: [^()]*?, SQL: ).*\)$/s',
                fn (array $match): string => $match[1].($placeholders ?? self::FILTERED).')',
                $value,
            );

            $exception->setValue(self::scrubText($value));
        }

        $event->setExceptions($exceptions);
    }

    private static function scrubText(string $text): string
    {
        return (string) preg_replace(self::SENSITIVE_TEXT, self::FILTERED, $text);
    }

    private static function scrubRequest(Event $event): Event
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
