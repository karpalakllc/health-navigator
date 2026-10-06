<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the Next.js web tier tell us who its visitor is.
 *
 * Every server-side render and every browser write reaches this API from the web
 * tier's egress address, so without this `$request->ip()` is the web server and
 * every IP-keyed limiter (`api`, `api-login`, …) meters the whole site as one
 * client. TrustProxies cannot fix that on its own: trusting X-Forwarded-For from
 * "*" lets any caller pick its own bucket, and a CIDR list cannot describe a
 * serverless/PaaS web tier whose egress addresses change.
 *
 * So the web tier authenticates instead of being recognised by address. It sends
 * `X-Web-Tier-Auth: <WEB_TIER_SECRET>` and `X-Client-IP: <visitor>`; when the
 * secret matches, the visitor becomes the request's client address. Otherwise
 * X-Client-IP is discarded and the TrustProxies result stands untouched.
 *
 * Ordering: this is appended to the *global* stack, so it runs after
 * TrustProxies (which configures the trusted-proxy set for this request) and
 * before any route middleware — ThrottleRequests included — resolves a limiter
 * key. It must not run before TrustProxies; see resolveTo().
 */
class TrustWebTierClientIp
{
    /** Shorter secrets are treated as unset (and refused by platform:preflight). */
    public const MIN_SECRET_LENGTH = 32;

    public const AUTH_HEADER = 'X-Web-Tier-Auth';

    public const CLIENT_IP_HEADER = 'X-Client-IP';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $this->authenticatedClientIp($request);

        // Neither header may outlive this middleware. X-Client-IP from an
        // unauthenticated caller is plain user input, and the secret must not
        // reach anything that records request headers (exception reporters,
        // request logging).
        // The server bag holds its own copy (HTTP_X_WEB_TIER_AUTH, …), which is
        // what $_SERVER-based reporters read, so both have to go.
        foreach ([self::AUTH_HEADER, self::CLIENT_IP_HEADER] as $header) {
            $request->headers->remove($header);
            $request->server->remove('HTTP_'.strtoupper(str_replace('-', '_', $header)));
        }

        if ($clientIp !== null) {
            $this->resolveTo($request, $clientIp);
        }

        return $next($request);
    }

    private function authenticatedClientIp(Request $request): ?string
    {
        $secret = (string) config('zdravje.web_tier.secret', '');
        $presented = $request->headers->get(self::AUTH_HEADER);

        if (strlen($secret) < self::MIN_SECRET_LENGTH || ! is_string($presented)) {
            return null;
        }

        if (! hash_equals($secret, $presented)) {
            return null;
        }

        $clientIp = trim((string) $request->headers->get(self::CLIENT_IP_HEADER, ''));

        $valid = filter_var($clientIp, FILTER_VALIDATE_IP);

        return $valid === false ? null : $valid;
    }

    /**
     * Make $request->ip() return the visitor, without disturbing anything else.
     *
     * The client address is read from REMOTE_ADDR, or from X-Forwarded-For when
     * REMOTE_ADDR is a trusted proxy. Rewriting REMOTE_ADDR and dropping the
     * forwarding headers makes both paths agree on the visitor, whatever the
     * trusted-proxy configuration — including a visitor address that happens to
     * fall inside a trusted CIDR.
     *
     * Dropping X-Forwarded-Proto would also change the scheme, though: whether
     * the original hop was HTTPS was decided by the *original* REMOTE_ADDR's
     * trust, and a wrong scheme breaks generated and signed URLs. So the scheme
     * is resolved first, under TrustProxies' rules, and pinned into the server
     * bag. This is why the middleware has to run after TrustProxies.
     */
    private function resolveTo(Request $request, string $clientIp): void
    {
        $secure = $request->isSecure();

        $request->server->set('HTTPS', $secure ? 'on' : 'off');
        $request->server->set('REMOTE_ADDR', $clientIp);

        foreach (['X-Forwarded-For', 'X-Forwarded-Proto', 'X-Forwarded-Port', 'X-Forwarded-Host', 'X-Forwarded-Prefix', 'Forwarded'] as $header) {
            $request->headers->remove($header);
        }
    }
}
