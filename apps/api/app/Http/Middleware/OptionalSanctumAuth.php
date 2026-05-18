<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class OptionalSanctumAuth
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token !== null) {
            $accessToken = PersonalAccessToken::findToken($token);

            if ($accessToken?->tokenable !== null) {
                $user = $accessToken->tokenable;
                Auth::setUser($user);
                $request->setUserResolver(static fn () => $user);
            }
        }

        return $next($request);
    }
}
