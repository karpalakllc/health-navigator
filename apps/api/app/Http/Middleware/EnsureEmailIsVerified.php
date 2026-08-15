<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks contributing actions until the account's address is verified.
 *
 * Replaces the framework's `verified` alias so the refusal uses this API's
 * envelope — a localised message plus a stable `code` the client can branch on
 * to offer "resend verification" — rather than a bare English abort(403).
 */
class EnsureEmailIsVerified
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return ApiResponse::errorCode('auth.email_unverified', 403);
        }

        return $next($request);
    }
}
