<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Http\Responses\ApiResponse;
use App\Services\AnalyticsService;
use App\Support\UnifiedSearch;
use App\Support\UnifiedSearchCoordinator;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    public function __invoke(SearchRequest $request, UnifiedSearchCoordinator $search): JsonResponse
    {
        $validated = $request->validated();

        if (! empty($validated['q'])) {
            $this->analytics->record('search.query', $request->user(), [
                'q' => $validated['q'],
            ]);
        }

        return ApiResponse::success(
            $search->search(
                q: $validated['q'] ?? null,
                city: isset($validated['city']) ? trim($validated['city']) : null,
                perPage: $validated['per_page'] ?? UnifiedSearch::DEFAULT_PER_VERTICAL,
            ),
        );
    }
}
