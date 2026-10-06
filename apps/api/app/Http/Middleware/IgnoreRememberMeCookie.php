<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nothing in this app signs in with "remember me": the admin login no longer
 * offers it (App\Filament\Pages\Auth\Login) and the API uses Sanctum tokens. A
 * remember cookie issued before that change would still sign its holder back
 * in once the session expired — skipping the password and the second factor —
 * so the web guard is never shown one, and the browser is told to drop it.
 *
 * Runs after EncryptCookies (the guard reads the decrypted value) and before
 * anything resolves the user; the guard reads the cookie lazily, from this
 * same request object.
 */
class IgnoreRememberMeCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard && $request->cookies->has($name = $guard->getRecallerName())) {
            $request->cookies->remove($name);
            Cookie::queue(Cookie::forget($name));
        }

        return $next($request);
    }
}
