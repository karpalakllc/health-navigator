<?php

namespace App\Models;

use App\Enums\FacilityType;
use App\Support\ScriptInsensitiveSearch;
use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'type',
        'description',
        'city',
        'address',
        'latitude',
        'longitude',
        'has_emergency_services',
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
            'latitude' => 'float',
            'longitude' => 'float',
            'has_emergency_services' => 'boolean',
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
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_facility')->withTimestamps();
    }

    public function hasMapCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
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
        return ScriptInsensitiveSearch::whereColumnMatches($query, 'city', $city);
    }

    /**
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public function scopeSearchName(Builder $query, string $term): Builder
    {
        return ScriptInsensitiveSearch::whereColumnMatches($query, 'name', $term);
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_published
            && ! $this->trashed()
            && in_array($this->type?->value, FacilityType::clinicalValues(), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type?->value,
            'city' => $this->city,
            'description' => $this->description,
        ];
    }

    public function searchableAs(): string
    {
        return 'facilities';
    }
}
