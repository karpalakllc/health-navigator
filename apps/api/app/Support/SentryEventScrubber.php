<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Throwable;

/**
 * Sentry `before_send` / `before_send_transaction` / `before_breadcrumb` hooks:
 * strip credentials, contact details and health data from what the SDK attaches
 * to events.
 *
 * send_default_pii=false already drops cookies, the client IP and sensitive
 * headers, but the SDK still records the request body, the URL and its query
 * string verbatim — a failed login or password reset would otherwise ship the
 * password, and a verification or reset link its token and signature. Triage
 * `answers`/`values` are symptoms, so they are health data. Exception messages
 * carry data too: a QueryException interpolates its bindings into the SQL.
 *
 * What is covered: the request (body, query string, URL query), exception
 * values, the event message with its params and formatted form, and every
 * breadcrumb's message and metadata (log context included). What is not: other
 * event fields such as `extra`, `contexts` and tags — nothing in this app puts
 * personal data there, and free text is only pattern-scrubbed, so a secret in a
 * format the patterns below do not know about can still get through.
 *
 * A class (not a closure in config/sentry.php) so `php artisan config:cache`
 * can serialise the config.
 */
final class SentryEventScrubber
{
    public const FILTERED = '[Filtered]';

    /** Matches password, current_password, reset_token, signature, new_email, triage answers, ... */
    private const SENSITIVE_KEY = '/pass(word)?|token|secret|signature|api_?key|authori[sz]ation|e-?mail|^hash$|^answers$|^values$/i';

    /** Patterns redacted from free text (exception messages, log messages, breadcrumbs). */
    private const SENSITIVE_TEXT = [
        // Email addresses, wherever they sit ("/users/jane@example.com" included).
        // The domain must end in a letters-only label that is the last one and not
        // a file extension, so "/www/x@2x.png" and "x@cdn.example.org.txt" stay.
        '/(?<![A-Za-z0-9._%+-])[A-Za-z0-9._%+-]+@(?:[A-Za-z0-9-]+\.)+(?!(?:png|jpe?g|gif|svg|webp|avif|ico|css|js|php|txt|log|json)\b)[A-Za-z]{2,}\b(?!\.[A-Za-z0-9])/',
        // bcrypt / argon2 password hashes.
        '/\$2[aby]?\$\d{2}\$[.\/A-Za-z0-9]{53}/',
        '/\$argon2(?:id|i|d)\$[^\s\'"]+/',
        // Sanctum plain-text tokens: "<id>|<SANCTUM_TOKEN_PREFIX><40 chars><crc32>".
        '/\b\d+\|[A-Za-z0-9_]*[A-Za-z0-9]{40,}/',
    ];

    /**
     * Candidate runs for long secrets (reset tokens, signatures, base64 keys).
     * Bounded on both sides so a run is always taken whole; "/" is excluded so
     * file paths survive, and isLongSecret() decides what each run is.
     */
    private const TOKEN_RUN = '/(?<![A-Za-z0-9+_-])[A-Za-z0-9+_-]{32,}={0,2}(?![A-Za-z0-9+_=-])/';

    /** A word in an identifier: lower case or Capitalised, maybe camel-cased or numbered ("add", "AuthController", "step2"). */
    private const IDENTIFIER_WORD = '/^(?:[A-Z]?[a-z]{2,})+\d*$/';

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /** A 40-character hex digest: a git commit or other SHA-1, not a secret we issue. */
    private const SHA1 = '/^[0-9a-f]{40}$/i';

    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        self::scrubExceptions($event, $hint);

        $message = $event->getMessage();

        if ($message !== null && $message !== '') {
            $event->setMessage(
                self::scrubText($message),
                array_map(
                    fn (mixed $param): mixed => is_string($param) ? self::scrubText($param) : $param,
                    $event->getMessageParams(),
                ),
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

    /**
     * Breadcrumbs ride along with every later event, and sentry-laravel records
     * each log line as one with its context as metadata — a Log::warning() with
     * an exception message in the context would otherwise bypass beforeSend().
     */
    public static function beforeBreadcrumb(Breadcrumb $breadcrumb): ?Breadcrumb
    {
        $message = $breadcrumb->getMessage();

        if ($message !== null && $message !== '') {
            $breadcrumb = $breadcrumb->withMessage(self::scrubText($message));
        }

        foreach ($breadcrumb->getMetadata() as $name => $value) {
            $breadcrumb = $breadcrumb->withMetadata(
                (string) $name,
                preg_match(self::SENSITIVE_KEY, (string) $name) ? self::FILTERED : self::scrubValue($value),
            );
        }

        return $breadcrumb;
    }

    /**
     * Free-text scrubbing applied through nested context: sensitive keys are
     * dropped, strings are pattern-scrubbed, a Throwable is reduced to its class
     * and scrubbed message.
     */
    private static function scrubValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::scrubText($value);
        }

        if ($value instanceof Throwable) {
            return $value::class.': '.self::scrubText($value->getMessage());
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = is_string($key) && preg_match(self::SENSITIVE_KEY, $key)
                ? self::FILTERED
                : self::scrubValue($item);
        }

        return $value;
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
        $text = (string) preg_replace(self::SENSITIVE_TEXT, self::FILTERED, $text);

        return (string) preg_replace_callback(
            self::TOKEN_RUN,
            fn (array $match): string => self::scrubRun($match[0]),
            $text,
        );
    }

    /**
     * "_" is part of base64url, so a run containing it is judged whole first: a
     * long one with no identifier-like word in it that mixes upper case, lower
     * case and digits is a secret. Otherwise it is an identifier (migration
     * name, snake_case key, "z360_" token prefix) and each segment is judged on
     * its own, so a long secret behind a prefix still goes.
     */
    private static function scrubRun(string $run): string
    {
        if (! str_contains($run, '_')) {
            return self::isLongSecret($run) ? self::FILTERED : $run;
        }

        $segments = explode('_', $run);

        if (self::isUnderscoredSecret($run, $segments)) {
            return self::FILTERED;
        }

        return implode('_', array_map(
            fn (string $segment): string => self::isLongSecret($segment) ? self::FILTERED : $segment,
            $segments,
        ));
    }

    /**
     * @param  list<string>  $segments
     */
    private static function isUnderscoredSecret(string $run, array $segments): bool
    {
        if (strlen(rtrim($run, '=')) < 32) {
            return false;
        }

        foreach ($segments as $segment) {
            if (preg_match(self::IDENTIFIER_WORD, $segment)) {
                return false;
            }
        }

        return preg_match('/[A-Z]/', $run) === 1 && preg_match('/[a-z]/', $run) === 1 && preg_match('/\d/', $run) === 1;
    }

    private static function isLongSecret(string $run): bool
    {
        if (strlen(rtrim($run, '=')) < 32 || preg_match(self::UUID, $run) || preg_match(self::SHA1, $run)) {
            return false;
        }

        // A digit and a letter: long plain words and numbers are not secrets.
        return preg_match('/\d/', $run) === 1 && preg_match('/[A-Za-z]/', $run) === 1;
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
