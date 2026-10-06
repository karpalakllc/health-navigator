<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Http\Responses\ApiResponse;
use App\Services\AnalyticsService;
use App\Support\UnifiedSearch;
use App\Support\UnifiedSearchCoordinator;
use Illuminate\Http\JsonResponse;

use function Illuminate\Support\defer;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, UnifiedSearchCoordinator $search, AnalyticsService $analytics): JsonResponse
    {
        $validated = $request->validated();

        if (! empty($validated['q'])) {
            // Anonymous by construction: only the normalised term is counted,
            // never the caller. Deferred until after the response is sent so it
            // never slows search — and unlike a queued job, it leaves no payload
            // holding the raw query behind (failed_jobs, queue backends).
            $term = (string) $validated['q'];

            defer(static fn () => $analytics->recordSearchTerm($term));
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
