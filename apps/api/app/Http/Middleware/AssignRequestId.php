<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

/**
 * Gives every request a correlation ID and returns it as `X-Request-Id`.
 *
 * The Next.js web tier forwards the ID it assigned to the page render
 * (apps/web/src/proxy.ts → lib/api/client-ip.ts), so every API call one page
 * makes shares it, and a support report quoting it finds both sides' logs.
 *
 * An incoming value is only kept when it matches REQUEST_ID_PATTERN: letters,
 * digits and hyphens, 8–64 characters, which covers a UUID and the IDs PaaS
 * edges mint. Anything else (newlines, separators, a megabyte of text) is
 * caller-supplied input headed for every log line and is replaced with a fresh
 * UUID. The ID only correlates; it is never trusted for anything.
 *
 * Laravel's Context carries it into every log record of this request (the
 * `extra` field) and into the payload of any job dispatched from it, so a
 * queued mail's failure is logged under the request that sent it.
 *
 * Prepended to the global stack, so it runs before maintenance mode, routing
 * and exception rendering hand back their responses: errors carry it too.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public const CONTEXT_KEY = 'request_id';

    public const REQUEST_ID_PATTERN = '/\A[A-Za-z0-9-]{8,64}\z/';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = self::accept($request->headers->get(self::HEADER)) ?? (string) Str::uuid();

        $request->headers->set(self::HEADER, $requestId);
        Context::add(self::CONTEXT_KEY, $requestId);
        configureScope(static function (Scope $scope) use ($requestId): void {
            $scope->setTag('request_id', $requestId);
        });

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    public static function accept(?string $incoming): ?string
    {
        return is_string($incoming) && preg_match(self::REQUEST_ID_PATTERN, $incoming) === 1
            ? $incoming
            : null;
    }
}
