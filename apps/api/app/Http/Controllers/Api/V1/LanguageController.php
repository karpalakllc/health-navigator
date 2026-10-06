<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LanguageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Language;
use App\Support\TaxonomyCache;
use Illuminate\Http\JsonResponse;

class LanguageController extends Controller
{
    /**
     * The languages a visitor can filter doctors by (GET /doctors?language=):
     * published ones spoken by at least one published doctor, so the filter
     * never offers a choice that can only come back empty.
     */
    public function index(): JsonResponse
    {
        $payload = TaxonomyCache::remember(TaxonomyCache::LANGUAGES, 'index', fn () => LanguageResource::collection(
            Language::query()
                ->published()
                ->whereHas('doctors', fn ($q) => $q->published())
                ->withCount([
                    'doctors as doctors_count' => fn ($q) => $q->published(),
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        )->resolve());

        return ApiResponse::success($payload);
    }
}
