<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListProductsRequest;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductListResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Support\PharmacyCatalog;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Product::query()
            ->published()
            ->orderBy('name');

        PharmacyCatalog::withFromPrice($query);
        PharmacyCatalog::withOfferCount($query);

        if (! empty($validated['q'])) {
            $query->searchName($validated['q']);
        }

        if (! empty($validated['category'])) {
            $query->category($validated['category']);
        }

        if (! empty($validated['pharmacy'])) {
            $query->withActiveOfferAtPharmacy($validated['pharmacy']);
        }

        $perPage = $validated['per_page'] ?? 15;

        $paginator = $query->paginate($perPage)->withQueryString();

        return ApiResponse::paginated(
            $paginator,
            ProductListResource::collection($paginator),
        );
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $offersQuery = PharmacyCatalog::activeOffersRelation($product);
        $offersTotal = (clone $offersQuery)->count();

        $offers = $offersQuery
            ->limit(Product::MAX_EMBEDDED_OFFERS)
            ->get();

        return ApiResponse::success(new ProductDetailResource($product, $offers, $offersTotal));
    }
}
