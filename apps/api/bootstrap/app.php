<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Http\Middleware\EnsureRegistrationsEnabled;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\IgnoreRememberMeCookie;
use App\Http\Middleware\OptionalSanctumAuth;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\TrustWebTierClientIp;
use App\Http\Responses\ApiResponse;
use App\Support\FrontendUrl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'module' => EnsureModuleEnabled::class,
            'registrations' => EnsureRegistrationsEnabled::class,
            'maintenance' => EnsureNotInMaintenance::class,
            'auth.sanctum.optional' => OptionalSanctumAuth::class,
            // Overrides the framework alias so refusals use this API's envelope.
            'verified' => EnsureEmailIsVerified::class,
        ]);

        // The API runs behind a PaaS edge (see infra/deploy.md), so the socket IP is
        // the load balancer's. Without this every $request->ip() rate limiter keys on
        // one shared value and a handful of failed logins locks out every user.
        // Proxy list is config-driven (config/trustedproxy.php) so it survives config:cache.
        // X_FORWARDED_HOST is deliberately absent. Trusting it lets anyone whose
        // header reaches the app change the host that URL::temporarySignedRoute()
        // builds from — which would have the platform mail a real user a genuine,
        // correctly-signed verification link pointing at a host they control.
        // Nothing here needs it. Incoming Host headers are constrained to APP_URL's
        // domain by trustHosts() below, and queued mail — where verification links
        // are built — has no request at all, so those links come from APP_URL.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        // The web tier proves itself with a shared secret rather than an address
        // (its egress IPs are not stable enough for TRUSTED_PROXIES) and hands us
        // the visitor's address. Appended globally so it runs after TrustProxies,
        // whose scheme decision it preserves, and before any route throttle.
        $middleware->append(TrustWebTierClientIp::class);

        // Global rather than per group so error pages, 404s and the panel's
        // Livewire endpoints carry them too.
        $middleware->append(SetSecurityHeaders::class);

        // Constrain the Host header to APP_URL's domain outside local/testing.
        // This is what stops a request claiming an arbitrary host and having
        // URL::temporarySignedRoute() mint a verification link on it.
        //
        // Deliberately not URL::forceRootUrl(): pinning the root globally also
        // pins Filament's asset URLs, so an admin served on any host or port other
        // than APP_URL loads assets from the wrong origin, and a signed link built
        // on APP_URL fails validation anywhere else. Validating the incoming host
        // solves the same problem without either side effect.
        $middleware->trustHosts();

        // Locale negotiation runs before anything that can emit a message, and is
        // scoped to the API so the Filament admin stays in config('app.locale').
        $middleware->api(prepend: [
            SetApiLocale::class,
            EnsureNotInMaintenance::class,
        ]);

        // The panel's own stack has it too; this covers the web group, which
        // carries Livewire's update endpoint (where the panel's forms submit).
        $middleware->web(append: [IgnoreRememberMeCookie::class]);

        // Baseline limit for every v1 route; the named limiters stay layered on top.
        $middleware->throttleApi('api');

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                abort(401, 'Unauthenticated.');
            }

            return route('filament.admin.auth.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // sentry-laravel unregisters the PHP SDK's own error listeners and relies
        // on this hook; without it nothing reaches Sentry. A no-op without a DSN.
        Integration::handles($exceptions);

        // Route middleware does not run for a request that matches no route (404,
        // 405), so SetApiLocale has not negotiated a language yet; do it here as
        // well. For a matched route this repeats the middleware's own answer.
        $isApi = static function (Request $request): bool {
            if (! $request->is('api/*')) {
                return false;
            }

            App::setLocale($request->getPreferredLanguage(SetApiLocale::SUPPORTED) ?? SetApiLocale::DEFAULT_LOCALE);

            return true;
        };

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::errorCode('errors.unauthenticated', 401);
            }
        });

        // `message` stays the framework's summary ("first error (and N more
        // errors)", localised via lang/{locale}.json) and `errors` keeps its
        // per-field shape: apps/web reads payload.errors.<field>[0], then payload.message.
        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($e->response === null && $isApi($request)) {
                return ApiResponse::error($e->getMessage(), $e->status, $e->errors(), 'validation.failed');
            }
        });

        // A verification link is opened from a mail client, long after it was sent, so
        // the ordinary case is an expired signature. ValidateSignature aborts before
        // the controller runs, which would otherwise hand the user Laravel's raw 403
        // page instead of the /verify-email screen that offers them a fresh link.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) use ($isApi) {
            if ($request->routeIs('verification.verify')) {
                return redirect()->away(FrontendUrl::to('/verify-email?status=invalid'));
            }

            if ($isApi($request)) {
                return ApiResponse::errorCode('errors.forbidden', 403);
            }
        });

        // By the time render callbacks run, the framework has already turned an
        // AuthorizationException into an AccessDeniedHttpException and a missing
        // model into a NotFoundHttpException, so the status is what to branch on.
        $httpCodes = [
            401 => 'errors.unauthenticated',
            403 => 'errors.forbidden',
            404 => 'errors.not_found',
            405 => 'errors.method_not_allowed',
            429 => 'errors.too_many_requests',
        ];

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi, $httpCodes) {
            $status = $e->getStatusCode();

            if (! isset($httpCodes[$status]) || ! $isApi($request)) {
                return null;
            }

            // A policy's own denial message (Response::deny('...')) is written for
            // the user; the framework's default English one is not.
            $previous = $e->getPrevious();
            $denial = $previous instanceof AuthorizationException ? $previous->getMessage() : '';

            $response = $denial !== '' && $denial !== (new AuthorizationException)->getMessage()
                ? ApiResponse::error($denial, $status, code: $httpCodes[$status])
                : ApiResponse::errorCode($httpCodes[$status], $status);

            // Retry-After (429) and Allow (405) are part of the answer.
            return $response->withHeaders($e->getHeaders());
        });

        // Anything else is a 500. Outside debug, never echo the exception message
        // (it can carry SQL or file paths); in debug, keep the framework's trace.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if ($e instanceof HttpExceptionInterface
                || $e instanceof HttpResponseException
                || $e instanceof ValidationException
                || $e instanceof AuthenticationException
                || config('app.debug')
                || ! $isApi($request)) {
                return null;
            }

            return ApiResponse::errorCode('errors.server_error', 500);
        });

        // SetApiLocale sets these on matched routes; a request that matched no
        // route never reached it, yet its error still varies by Accept-Language.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) use ($isApi) {
            if ($request->route() === null && $isApi($request)) {
                $response->headers->set('Content-Language', App::getLocale());
                $vary = $response->headers->get('Vary');
                $response->headers->set('Vary', $vary ? $vary.', Accept-Language' : 'Accept-Language');
            }

            return $response;
        });
    })->create();
