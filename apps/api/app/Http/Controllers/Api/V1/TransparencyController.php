<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\TransparencyStats;
use Illuminate\Http\JsonResponse;

/**
 * GET /transparency: the public monthly moderation figures (TransparencyStats),
 * cached server side for an hour.
 */
class TransparencyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(TransparencyStats::cached());
    }
}
