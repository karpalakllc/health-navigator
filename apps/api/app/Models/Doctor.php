<?php

namespace App\Models;

use App\Support\ScriptInsensitiveSearch;
use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'slug',
        'full_name',
        'title',
        'subspecialty',
        'bio',
        'years_experience',
        'education',
        'consultation_fee_note',
        'avatar_url',
        'office_hours',
        'accepts_new_patients',
        'is_featured',
        'is_sponsored',
        'city',
        'phone',
        'email',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'office_hours' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'accepts_new_patients' => 'boolean',
            'is_featured' => 'boolean',
            'is_sponsored' => 'boolean',
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
     * @return BelongsToMany<Language, $this>
     */
    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'doctor_language')->withTimestamps();
    }

    /**
     * @return BelongsToMany<ClinicalInterest, $this>
     */
    public function clinicalInterests(): BelongsToMany
    {
        return $this->belongsToMany(ClinicalInterest::class, 'doctor_clinical_interest')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Procedure, $this>
     */
    public function procedures(): BelongsToMany
    {
        return $this->belongsToMany(Procedure::class, 'doctor_procedure')->withTimestamps();
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
        return ScriptInsensitiveSearch::whereColumnMatches($query, 'city', $city);
    }

    /**
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    public function scopeSearchName(Builder $query, string $term): Builder
    {
        return ScriptInsensitiveSearch::whereColumnMatches($query, 'full_name', $term);
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

    public function shouldBeSearchable(): bool
    {
        return $this->is_published && ! $this->trashed();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['specialties' => fn ($relation) => $relation->published()]);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'full_name' => $this->full_name,
            'title' => $this->title,
            'subspecialty' => $this->subspecialty,
            'city' => $this->city,
            'specialty_names' => $this->specialties->pluck('name')->all(),
        ];
    }

    public function searchableAs(): string
    {
        return 'doctors';
    }
}
