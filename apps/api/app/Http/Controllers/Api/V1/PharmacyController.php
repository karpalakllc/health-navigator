<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListPharmaciesRequest;
use App\Http\Requests\Api\V1\ListPharmacyProductsRequest;
use App\Http\Resources\Api\V1\PharmacyDetailResource;
use App\Http\Resources\Api\V1\PharmacyListResource;
use App\Http\Resources\Api\V1\PharmacyShelfProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Facility;
use App\Support\PharmacyCatalog;
use App\Support\ScriptInsensitiveSearch;
use App\Support\ReviewSummary;
use Illuminate\Http\JsonResponse;

class PharmacyController extends Controller
{
    public function index(ListPharmaciesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = ReviewSummary::eagerLoad(Facility::query())
            ->published()
            ->pharmacy()
            ->orderBy('name');

        if (! empty($validated['city'])) {
            $query->cityContains($validated['city']);
        }

        if (! empty($validated['q'])) {
            $query->searchName($validated['q']);
        }

        $perPage = $validated['per_page'] ?? 15;

        $paginator = $query->paginate($perPage)->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            PharmacyListResource::collection($paginator),
        );
    }

    public function show(string $slug): JsonResponse
    {
        $pharmacy = $this->findPublishedPharmacy($slug);

        return ApiResponse::success(new PharmacyDetailResource($pharmacy));
    }

    public function products(string $slug, ListPharmacyProductsRequest $request): JsonResponse
    {
        $pharmacy = $this->findPublishedPharmacy($slug);
        $validated = $request->validated();

        $query = PharmacyCatalog::availableProductsRelation($pharmacy);

        if (! empty($validated['q'])) {
            ScriptInsensitiveSearch::whereColumnMatches($query, 'products.name', $validated['q']);
        }

        if (! empty($validated['category'])) {
            if ($query->getConnection()->getDriverName() === 'pgsql') {
                $query->where('products.category', 'ilike', $validated['category']);
            } else {
                $query->whereRaw('LOWER(products.category) = ?', [mb_strtolower($validated['category'])]);
            }
        }

        $perPage = $validated['per_page'] ?? 15;

        $paginator = $query->paginate($perPage)->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            PharmacyShelfProductResource::collection($paginator),
        );
    }

    protected function findPublishedPharmacy(string $slug): Facility
    {
        return ReviewSummary::eagerLoad(Facility::query())
            ->published()
            ->pharmacy()
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
