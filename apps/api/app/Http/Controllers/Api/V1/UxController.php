<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUxEventsRequest;
use App\Http\Responses\ApiResponse;
use App\Support\Ux\UxEventRecorder;
use App\Support\Ux\UxOverlayToken;
use App\Support\Ux\UxSchema;
use App\Support\Ux\UxStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Anonymous UX statistics (docs/ux-heatmaps.md).
 *
 * POST /ux/events is open: the web tier relays tracker batches here. Only
 * counters are written. The address is not stored with them; it is used for
 * the rate limit alone, as an HMAC of its network under the app key (the
 * `api-ux-events` limiter), a cache key that expires with its window.
 *
 * GET /ux/heatmap is for staff only, through the overlay token minted on the
 * admin „UX анализа“ page; without a valid one it answers 403 and reads
 * nothing.
 */
class UxController extends Controller
{
    public const TOKEN_HEADER = 'X-Ux-Overlay-Token';

    public function store(StoreUxEventsRequest $request, UxEventRecorder $recorder): Response
    {
        $recorder->record($request->clicks(), $request->views());

        return response()->noContent();
    }

    public function heatmap(Request $request, UxStats $stats): JsonResponse
    {
        $grant = UxOverlayToken::verify($request->header(self::TOKEN_HEADER));

        if ($grant === null) {
            return ApiResponse::errorCode('errors.forbidden', 403)
                ->header('Cache-Control', 'no-store');
        }

        $validated = $request->validate([
            'route' => ['required', 'string', Rule::in(UxSchema::routes())],
            'vc' => ['required', 'string', Rule::in(UxSchema::VIEWPORT_CLASSES)],
            'wb' => ['nullable', 'integer', 'min:0', 'max:'.UxSchema::MAX_WIDTH],
        ]);

        $to = Carbon::today();
        $from = $to->copy()->subDays($grant['days'] - 1);
        $route = (string) $validated['route'];
        $viewportClass = (string) $validated['vc'];
        $widthBucket = isset($validated['wb']) ? (int) $validated['wb'] : null;

        return ApiResponse::success([
            'route' => $route,
            'viewport_class' => $viewportClass,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'x_buckets' => UxSchema::MAX_X + 1,
            'y_step' => UxSchema::Y_STEP,
            'cells' => $stats->heatmapCells($route, $viewportClass, $from, $to, $widthBucket),
            'page' => $stats->pageSummary($route, $viewportClass, $from, $to),
        ])->header('Cache-Control', 'no-store, private');
    }
}
