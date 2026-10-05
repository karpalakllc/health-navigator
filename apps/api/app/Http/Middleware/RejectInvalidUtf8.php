<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects API input that is not valid UTF-8 (e.g. `?city=%D1%5C`) with the
 * ordinary 422 validation envelope. Left alone it reaches the database, where
 * PostgreSQL refuses the bytes (SQLSTATE 22021) and the request becomes a 500;
 * it is a client error, and no field of this API accepts binary text.
 *
 * Checks the query string and the form/JSON body (keys and values, nested).
 * The path is already rejected with a 400 by Laravel's ValidatePathEncoding,
 * and a JSON body with invalid UTF-8 never decodes, so it arrives empty.
 */
final class RejectInvalidUtf8
{
    public function handle(Request $request, Closure $next): Response
    {
        $invalid = array_merge(
            self::invalidKeys($request->query->all()),
            self::invalidKeys($request->request->all()),
        );

        if ($invalid !== []) {
            throw ValidationException::withMessages(array_fill_keys(
                array_unique($invalid),
                [__('api.validation.invalid_encoding')],
            ));
        }

        return $next($request);
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return list<string> dotted keys whose key or value is not valid UTF-8
     */
    private static function invalidKeys(array $input, string $prefix = ''): array
    {
        $invalid = [];

        foreach ($input as $key => $value) {
            $key = (string) $key;
            $dotted = $prefix.(mb_check_encoding($key, 'UTF-8') ? $key : mb_scrub($key, 'UTF-8'));

            if (! mb_check_encoding($key, 'UTF-8')) {
                $invalid[] = $dotted;
            } elseif (is_array($value)) {
                array_push($invalid, ...self::invalidKeys($value, $dotted.'.'));
            } elseif (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                $invalid[] = $dotted;
            }
        }

        return $invalid;
    }
}
