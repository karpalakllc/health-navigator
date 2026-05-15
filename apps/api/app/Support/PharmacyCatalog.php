<?php

namespace App\Support;

use App\Enums\FacilityType;
use App\Models\Facility;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class PharmacyCatalog
{
    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function withFromPrice(Builder $query): Builder
    {
        $sub = DB::table('pharmacy_product')
            ->join('facilities', 'facilities.id', '=', 'pharmacy_product.facility_id')
            ->whereColumn('pharmacy_product.product_id', 'products.id')
            ->where('facilities.is_published', true)
            ->where('facilities.type', FacilityType::Pharmacy->value)
            ->where('pharmacy_product.is_available', true)
            ->whereNull('facilities.deleted_at')
            ->selectRaw('MIN(pharmacy_product.price)');

        return $query->addSelect(['from_price' => $sub]);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function withOfferCount(Builder $query): Builder
    {
        $sub = DB::table('pharmacy_product')
            ->join('facilities', 'facilities.id', '=', 'pharmacy_product.facility_id')
            ->whereColumn('pharmacy_product.product_id', 'products.id')
            ->where('facilities.is_published', true)
            ->where('facilities.type', FacilityType::Pharmacy->value)
            ->where('pharmacy_product.is_available', true)
            ->whereNull('facilities.deleted_at')
            ->selectRaw('COUNT(*)');

        return $query->addSelect(['offer_count' => $sub]);
    }

    /**
     * @return BelongsToMany<Product, Facility>
     */
    public static function availableProductsRelation(Facility $pharmacy): BelongsToMany
    {
        return $pharmacy->products()
            ->published()
            ->wherePivot('is_available', true)
            ->orderBy('products.name');
    }

    /**
     * @return BelongsToMany<Facility, Product>
     */
    public static function activeOffersRelation(Product $product): BelongsToMany
    {
        return $product->pharmacies()
            ->published()
            ->pharmacy()
            ->wherePivot('is_available', true)
            ->orderBy('pharmacy_product.price');
    }
}
