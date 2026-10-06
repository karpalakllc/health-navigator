<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets browsers and the web tier's fetch cache reuse anonymous, identical-for-
 * everyone GET payloads: Cache-Control: public with a short max-age, plus a
 * content ETag so a revalidation that has not changed costs a 304 with no body.
 *
 *   cache.public       taxonomies, 300 s (also cached server side, TaxonomyCache)
 *   cache.public:60    directory and product lists / profiles, 60 s, so an
 *                      admin edit is visible to any HTTP cache within a minute
 *
 * Only successful responses are marked. Laravel's cache.headers middleware
 * would also mark a 404 or 503 public, and a module switched back on would
 * then stay "off" in shared caches for max-age.
 *
 * A request carrying credentials (an Authorization header, or a user resolved
 * by an earlier middleware) is left alone: it gets the framework's
 * `no-cache, private`. The public answer adds `Vary: Authorization` (the API
 * locale middleware adds Accept-Language), so a shared cache keeps the
 * anonymous copy away from a signed-in request even if it would store one.
 *
 * Never put this on a route that varies by viewer (auth.sanctum.optional,
 * viewer_review): a shared cache would hand one user's payload to another.
 */
class SetPublicCacheHeaders
{
    public const DEFAULT_MAX_AGE = 300;

    public function handle(Request $request, Closure $next, int|string $maxAge = self::DEFAULT_MAX_AGE): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200) {
            return $response;
        }

        if ($request->headers->has('Authorization') || $request->user() !== null) {
            return $response;
        }

        if ($response->headers->getCookies() !== []) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return $response;
        }

        $response->setPublic();
        $response->setMaxAge((int) $maxAge);
        $response->setEtag(hash('xxh128', $content));
        $response->setVary('Authorization', false);

        // Clears the body and switches to 304 when If-None-Match matches.
        $response->isNotModified($request);

        return $response;
    }
}
