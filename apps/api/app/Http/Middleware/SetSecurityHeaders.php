<?php

namespace App\Http\Middleware;

use App\Support\DeploymentEnvironment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser-security headers for everything Laravel serves.
 *
 * Two groups, because the two halves of this app are consumed differently:
 *
 *  - /api/* is JSON fetched cross-origin by the web app (CORS) and by mobile.
 *    Nothing in it is ever a document, so its CSP forbids everything and it may
 *    not be framed.
 *  - everything else is the Filament admin panel (and the few web routes around
 *    it). Filament ships inline scripts and styles and Alpine evaluates its
 *    expressions with `new Function`, so a script-src without 'unsafe-inline'
 *    and 'unsafe-eval' blanks the panel. The directives that cannot break
 *    rendering — no framing, no <base> hijack, no plugins, forms only to us — are
 *    enforced; the full fetch policy is sent Report-Only, so violations show in
 *    the browser console without breaking anything (infra/deploy.md).
 *
 * Deliberately absent: Cross-Origin-Resource-Policy. Media is embedded by the web
 * app from another origin through <img>, and same-site/same-origin would block
 * it; /storage and the build assets are served by the web server anyway, not
 * through this stack.
 *
 * Headers a response already carries are left alone, so a route can opt into a
 * different policy by setting its own.
 */
class SetSecurityHeaders
{
    public const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()';

    public const API_CSP = "default-src 'none'; frame-ancestors 'none'";

    public const ADMIN_CSP = "frame-ancestors 'none'; base-uri 'self'; object-src 'none'; form-action 'self'";

    public const ADMIN_CSP_REPORT_ONLY = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'; form-action 'self'";

    /** One year. No includeSubDomains/preload: other hosts under the apex are not ours to commit. */
    public const HSTS = 'max-age=31536000';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => self::PERMISSIONS_POLICY,
            'X-Frame-Options' => 'DENY',
        ];

        if ($request->is('api', 'api/*')) {
            $headers['Content-Security-Policy'] = self::API_CSP;
        } else {
            $headers['Content-Security-Policy'] = self::ADMIN_CSP;
            $headers['Content-Security-Policy-Report-Only'] = self::ADMIN_CSP_REPORT_ONLY;
        }

        // Only over TLS (a header on plain HTTP is ignored, and pinning a dev
        // machine's localhost to HTTPS is a nuisance) and only when deployed.
        if ($request->isSecure() && DeploymentEnvironment::isDeployed()) {
            $headers['Strict-Transport-Security'] = self::HSTS;
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
