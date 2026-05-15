<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function staff(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'message' => 'Staff platform route.',
            'role' => $request->user()->role->value,
        ]);
    }

    public function admin(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'message' => 'Admin platform route.',
            'role' => $request->user()->role->value,
        ]);
    }
}
