<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationsEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! SiteSetting::current()->registrations_enabled) {
            return ApiResponse::errorCode('registration.disabled', 403);
        }

        return $next($request);
    }
}
