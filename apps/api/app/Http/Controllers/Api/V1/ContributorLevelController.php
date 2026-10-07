<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SiteSetting;
use App\Support\Levels\ContributorLevels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * W8-C contributor levels (docs/levels.md).
 *
 * - GET /me/levels: the member's own levels and what the next ones need.
 * - GET /community/leaderboards: last month's „Најкорисни рецензенти“ and
 *   „Најактивни во форумот“, by username only (the forum list is null while
 *   the forum module is off).
 */
class ContributorLevelController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(ContributorLevels::progressFor($request->user()));
    }

    public function leaderboards(): JsonResponse
    {
        $boards = ContributorLevels::leaderboards();

        if (! SiteSetting::current()->public_forum) {
            $boards['forum'] = null;
        }

        return ApiResponse::success($boards);
    }
}
