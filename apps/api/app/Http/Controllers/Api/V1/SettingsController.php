<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function publicSettings(): JsonResponse
    {
        $settings = SiteSetting::current();

        return ApiResponse::success([
            ...$settings->publicFlags(),
            ...$settings->publicBranding(),
        ]);
    }
}
