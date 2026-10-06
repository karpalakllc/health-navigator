<?php

namespace App\Models;

use App\Models\Concerns\DeletesReplacedMedia;
use App\Models\Concerns\InvalidatesTaxonomyCache;
use App\Support\MacedonianSearchVariants;
use App\Support\ScriptInsensitiveSearch;
use App\Support\TaxonomyCache;
use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use DeletesReplacedMedia, HasFactory, InvalidatesTaxonomyCache, Searchable, SoftDeletes;

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

    /**
     * GET /specialties embeds published-doctor counts; GET /home/highlights
     * counts doctors per specialty and city and names review targets.
     *
     * @return list<string>
     */
    public static function taxonomyCacheGroups(): array
    {
        return [TaxonomyCache::SPECIALTIES, TaxonomyCache::HOME_HIGHLIGHTS];
    }

    /**
     * The uploaded photo. Despite its name the column holds a media-disk path
     * (or, on legacy rows, an external URL); MediaUrl resolves either.
     *
     * @return list<string>
     */
    protected function mediaPathColumns(): array
    {
        return ['avatar_url'];
    }

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
     * Public free-text search: the name, or the name of a published specialty,
     * so "кардиолог" / "kardio" finds cardiologists, not only doctors named so.
     *
     * Written as `id IN (name matches UNION members of the matching
     * specialties)` rather than `name ILIKE … OR EXISTS (specialty …)`: an OR
     * with a correlated subquery cannot use the full_name trigram index, so
     * every search filtered all published doctors row by row. Each arm of the
     * union uses its own index (trigram; doctor_specialty's specialty_id), and
     * the specialties matching the term — a handful of rows — are resolved
     * first so the pivot arm is a plain IN list, or absent when none match.
     *
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    public function scopeSearchNameOrSpecialty(Builder $query, string $term): Builder
    {
        $specialtyIds = Specialty::query()->published()->searchName($term)->pluck('id');

        $matches = ScriptInsensitiveSearch::whereColumnMatches(
            static::query()->withoutGlobalScopes()->select('doctors.id'),
            'doctors.full_name',
            $term,
        )->toBase();

        if ($specialtyIds->isNotEmpty()) {
            $matches->union(
                DB::table('doctor_specialty')->select('doctor_id')->whereIn('specialty_id', $specialtyIds),
            );
        }

        return $query->whereIn('doctors.id', $matches);
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
            // Sortable (config/scout.php): unified search breaks relevance ties
            // with it, as the SQL path orders featured first.
            'is_featured' => (bool) $this->is_featured,
            'specialty_names' => $this->specialties->pluck('name')->all(),
            // Meilisearch does not transliterate: "kardio" must find "Кардиологија"
            // as the SQL path (ScriptInsensitiveSearch) does.
            'specialty_names_latin' => $this->specialties
                ->map(fn (Specialty $specialty): string => MacedonianSearchVariants::cyrillicToLatin($specialty->name))
                ->all(),
        ];
    }

    /**
     * Prefixed like Scout's default, so SCOUT_PREFIX moves the data and the
     * index settings (config/scout.php) to the same index.
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'doctors';
    }
}
