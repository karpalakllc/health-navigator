<?php

namespace App\Models;

use App\Enums\FacilityType;
use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'type',
        'description',
        'city',
        'address',
        'phone',
        'email',
        'website',
        'avatar_url',
        'office_hours',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => FacilityType::class,
            'office_hours' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Doctor, $this>
     */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class)
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }

    /**
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'pharmacy_product')
            ->withPivot(['price', 'currency', 'is_available', 'price_updated_at'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public function scopeClinical(Builder $query): Builder
    {
        return $query->whereIn('type', FacilityType::clinicalValues());
    }

    /**
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public function scopePharmacy(Builder $query): Builder
    {
        return $query->where('type', FacilityType::Pharmacy);
    }

    public function isPharmacy(): bool
    {
        return $this->type === FacilityType::Pharmacy;
    }

    /**
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public function scopeCityContains(Builder $query, string $city): Builder
    {
        $term = '%'.addcslashes($city, '%_\\').'%';

        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $query->where('city', 'ilike', $term);
        }

        return $query->whereRaw('LOWER(city) LIKE ?', ['%'.mb_strtolower($city).'%']);
    }

    /**
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public function scopeSearchName(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $query->where('name', 'ilike', $like);
        }

        return $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%']);
    }
}
