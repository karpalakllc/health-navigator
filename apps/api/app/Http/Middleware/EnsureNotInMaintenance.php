<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInMaintenance
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! SiteSetting::current()->maintenance_mode) {
            return $next($request);
        }

        if ($request->is('api/v1/settings/public', 'api/v1/health')) {
            return $next($request);
        }

        return ApiResponse::error('Сервисот е привремено недостапен поради одржување.', 503);
    }
}
