<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SpecialtyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Specialty;
use App\Support\TaxonomyCache;
use Illuminate\Http\JsonResponse;

class SpecialtyController extends Controller
{
    public function index(): JsonResponse
    {
        $payload = TaxonomyCache::remember(TaxonomyCache::SPECIALTIES, 'index', fn () => SpecialtyResource::collection(
            Specialty::query()
                ->published()
                ->withCount([
                    'doctors as doctors_count' => fn ($q) => $q->published(),
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        )->resolve());

        return ApiResponse::success($payload);
    }

    public function show(string $slug): JsonResponse
    {
        // firstOrFail() throws inside the closure, so a 404 is never cached.
        $payload = TaxonomyCache::remember(TaxonomyCache::SPECIALTIES, 'show:'.$slug, fn () => (new SpecialtyResource(
            Specialty::query()
                ->published()
                ->where('slug', $slug)
                ->withCount([
                    'doctors as doctors_count' => fn ($q) => $q->published(),
                ])
                ->firstOrFail(),
        ))->resolve());

        return ApiResponse::success($payload);
    }
}
