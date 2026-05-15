<?php

namespace App\Models;

use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'full_name',
        'title',
        'subspecialty',
        'bio',
        'years_experience',
        'education',
        'languages',
        'clinical_interests',
        'procedures',
        'consultation_fee_note',
        'avatar_url',
        'office_hours',
        'accepts_new_patients',
        'is_featured',
        'city',
        'phone',
        'email',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'clinical_interests' => 'array',
            'procedures' => 'array',
            'office_hours' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'accepts_new_patients' => 'boolean',
            'is_featured' => 'boolean',
            'years_experience' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Specialty, $this>
     */
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class)
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Facility, $this>
     */
    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class)
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
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
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
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    public function scopeSearchName(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $query->where('full_name', 'ilike', $like);
        }

        return $query->whereRaw('LOWER(full_name) LIKE ?', ['%'.mb_strtolower($term).'%']);
    }

    /**
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    public function scopeForSpecialtySlug(Builder $query, string $slug): Builder
    {
        return $query->whereHas('specialties', function (Builder $specialtyQuery) use ($slug): void {
            $specialtyQuery->where('slug', $slug)->published();
        });
    }

    /**
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}
