<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::errorCode('errors.unauthenticated', 401);
        }

        if (! in_array($user->role->value, $roles, true)) {
            return ApiResponse::errorCode('errors.forbidden', 403);
        }

        return $next($request);
    }
}
