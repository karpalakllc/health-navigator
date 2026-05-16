<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $settings = SiteSetting::current();

        $enabled = match ($module) {
            'guidance' => $settings->public_guidance,
            'products' => $settings->public_products,
            'pharmacies' => $settings->public_pharmacies,
            'forum' => $settings->public_forum,
            default => false,
        };

        if (! $enabled) {
            return ApiResponse::error('This module is not available yet.', 503);
        }

        return $next($request);
    }
}
