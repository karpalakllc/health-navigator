<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\PlatformHealth;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $result = PlatformHealth::check();

        return ApiResponse::success(
            [
                'status' => $result['healthy'] ? 'ok' : 'degraded',
                'checks' => $result['checks'],
            ],
            $result['healthy'] ? 200 : 503,
        );
    }
}
