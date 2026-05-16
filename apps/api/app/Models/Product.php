<?php

namespace App\Models;

use App\Enums\FacilityType;
use App\Support\ScriptInsensitiveSearch;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    public const MAX_EMBEDDED_OFFERS = 10;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'category',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Facility, $this>
     */
    public function pharmacies(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'pharmacy_product')
            ->withPivot(['price', 'currency', 'is_available', 'price_updated_at'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearchName(Builder $query, string $term): Builder
    {
        return ScriptInsensitiveSearch::whereColumnMatches($query, 'name', $term);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $query->where('category', 'ilike', $category);
        }

        return $query->whereRaw('LOWER(category) = ?', [mb_strtolower($category)]);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeWithActiveOfferAtPharmacy(Builder $query, string $pharmacySlug): Builder
    {
        return $query->whereHas('pharmacies', function (Builder $pharmacyQuery) use ($pharmacySlug): void {
            $pharmacyQuery
                ->where('slug', $pharmacySlug)
                ->where('type', FacilityType::Pharmacy)
                ->published()
                ->where('pharmacy_product.is_available', true)
                ->whereNotNull('pharmacy_product.price');
        });
    }
}
