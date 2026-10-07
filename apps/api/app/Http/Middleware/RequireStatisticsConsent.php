<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Statistics are collected only after the visitor accepted them in the
 * consent banner. The browser then sends `X-Z360-Consent: statistics` with
 * every statistics request (through the web tier's relays). Without it nothing
 * is stored and the answer is a plain 204.
 *
 * Global Privacy Control and Do Not Track are deliberately not read here: an
 * explicit "yes" in the banner overrides the generic browser signal, and
 * without the "yes" the header is absent anyway.
 */
class RequireStatisticsConsent
{
    public const HEADER = 'X-Z360-Consent';

    public const VALUE = 'statistics';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (strtolower(trim((string) $request->header(self::HEADER))) !== self::VALUE) {
            return response()->noContent();
        }

        return $next($request);
    }
}
