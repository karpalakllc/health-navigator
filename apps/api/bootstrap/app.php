<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Http\Middleware\EnsureRegistrationsEnabled;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\OptionalSanctumAuth;
use App\Http\Middleware\SetApiLocale;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        ]);

        // The API runs behind a PaaS edge (see infra/deploy.md), so the socket IP is
        // the load balancer's. Without this every $request->ip() rate limiter keys on
        // one shared value and a handful of failed logins locks out every user.
        // Proxy list is config-driven (config/trustedproxy.php) so it survives config:cache.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        // Locale negotiation runs before anything that can emit a message, and is
        // scoped to the API so the Filament admin stays in config('app.locale').
        $middleware->api(prepend: [
            SetApiLocale::class,
            EnsureNotInMaintenance::class,
        ]);

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
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::errorCode('errors.unauthenticated', 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return $e->getMessage() !== ''
                    ? ApiResponse::error($e->getMessage(), 403, code: 'errors.forbidden')
                    : ApiResponse::errorCode('errors.forbidden', 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::errorCode('errors.not_found', 404);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::errorCode('errors.too_many_requests', 429);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') && $e->getStatusCode() === 401) {
                return ApiResponse::errorCode('errors.unauthenticated', 401);
            }
        });
    })->create();
