<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Support\ReviewViews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * W8-B: the web reports which review cards were on a visitor's screen
 * (ReviewViews has the counting rules: only approved reviews on a published
 * profile). Anonymous; optional auth only so the author's own views are left
 * out. Counted only with the statistics-consent header
 * (statistics.consent middleware).
 */
class ReviewViewController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:'.ReviewViews::MAX_IDS],
            'ids.*' => ['integer', 'min:1', 'max:999999999999'],
        ]);

        $viewer = $request->user();

        $counted = ReviewViews::record(
            array_map('intval', $validated['ids']),
            (string) $request->ip(),
            $viewer instanceof User ? $viewer : null,
        );

        return ApiResponse::success(['counted' => $counted]);
    }
}
