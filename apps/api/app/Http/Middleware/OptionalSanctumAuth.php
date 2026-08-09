<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves a bearer token when one is present, without requiring it.
 *
 * Goes through the Sanctum guard rather than PersonalAccessToken::findToken(),
 * which performs only a hash lookup — it skips the expiry window
 * (SANCTUM_TOKEN_EXPIRATION_MINUTES), the token's own expires_at, provider
 * validation and the last_used_at update. A token expired months ago was
 * therefore still being treated as authenticated here.
 */
class OptionalSanctumAuth
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('sanctum')->check()) {
            Auth::shouldUse('sanctum');
        }

        return $next($request);
    }
}
