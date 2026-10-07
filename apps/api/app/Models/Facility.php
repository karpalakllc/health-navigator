<?php

namespace App\Models;

use App\Enums\FacilityType;
use App\Models\Concerns\DeletesReplacedMedia;
use App\Models\Concerns\HasImportDraftLifecycle;
use App\Models\Concerns\HasVerification;
use App\Models\Concerns\InvalidatesTaxonomyCache;
use App\Support\Import\ImportBookkeeping;
use App\Support\MacedonianSearchVariants;
use App\Support\OfficeHours;
use App\Support\ScriptInsensitiveSearch;
use App\Support\TaxonomyCache;
use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

class Facility extends Model
{
    /**
     * Urgent-care service (the public `type` filter of GET /urgent-care) =>
     * its flag column.
     */
    public const URGENT_CARE_SERVICES = [
        'ed' => 'has_emergency_services',
        'ems' => 'has_emergency_medical_service',
        'clinic' => 'has_on_duty_clinic',
        'dental' => 'has_dental_emergency',
    ];

    /** emergency_department_status (docs/urgent-care.md § Data). */
    public const ED_CONFIRMED = 'confirmed';

    public const ED_UNCONFIRMED_LIKELY = 'unconfirmed_likely';

    public const ED_NONE = 'none';

    public const ED_STATUSES = [self::ED_CONFIRMED, self::ED_UNCONFIRMED_LIKELY, self::ED_NONE];

    /** @use HasFactory<FacilityFactory> */
    use DeletesReplacedMedia, HasFactory, HasImportDraftLifecycle, HasVerification, InvalidatesTaxonomyCache, Searchable, SoftDeletes;

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
        'emergency_department_status',
        'has_emergency_medical_service',
        'has_on_duty_clinic',
        'has_dental_emergency',
        'is_open_24h',
        'emergency_hours',
        'emergency_phone',
        'urgent_care_note',
        'urgent_care_checked_at',
        'phone',
        'email',
        'website',
        'avatar_url',
        'cover_path',
        'office_hours',
        'is_published',
        'is_featured',
        'published_at',
    ];

    /**
     * GET /home/highlights names facilities and pharmacies as review targets.
     *
     * @return list<string>
     */
    public static function taxonomyCacheGroups(): array
    {
        return [TaxonomyCache::HOME_HIGHLIGHTS];
    }

    protected static function booted(): void
    {
        // The import rows about this facility go with it (ImportBookkeeping).
        static::forceDeleted(fn (Facility $facility) => ImportBookkeeping::forget(FieldProvenance::SUBJECT_FACILITY, (int) $facility->getKey()));

        // has_emergency_services is true exactly when the emergency
        // department is confirmed: the status wins when it changed, else the
        // flag (older code and the public filter still set the flag).
        static::saving(function (Facility $facility): void {
            if ($facility->isDirty('emergency_department_status')) {
                $facility->has_emergency_services = $facility->emergency_department_status === self::ED_CONFIRMED;
            } elseif ($facility->isDirty('has_emergency_services')) {
                if ($facility->has_emergency_services) {
                    $facility->emergency_department_status = self::ED_CONFIRMED;
                } elseif ($facility->emergency_department_status === self::ED_CONFIRMED) {
                    $facility->emergency_department_status = null;
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => FacilityType::class,
            'office_hours' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'has_emergency_services' => 'boolean',
            'has_emergency_medical_service' => 'boolean',
            'has_on_duty_clinic' => 'boolean',
            'has_dental_emergency' => 'boolean',
            'is_open_24h' => 'boolean',
            'urgent_care_evidence' => 'array',
            'urgent_care_checked_at' => 'datetime',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'import_last_seen_at' => 'datetime',
            'import_missing_runs' => 'integer',
        ];
    }

    /**
     * avatar_url is the logo, cover_path the wide header image.
     *
     * @return list<string>
     */
    protected function mediaPathColumns(): array
    {
        return ['avatar_url', 'cover_path'];
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
     * Images taken from the institution's website (logo, cover candidates).
     *
     * @return HasMany<FacilityMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(FacilityMedia::class)->orderBy('kind')->orderBy('position');
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_facility')->withTimestamps();
    }

    /**
     * The urgent service's hours as {day: hours}. The admin repeater hands
     * over a list of {day, hours} rows; those are folded into the map here.
     *
     * @return Attribute<array<string, string>|null, mixed>
     */
    protected function emergencyHours(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): ?array {
                $decoded = is_string($value) ? json_decode($value, true) : $value;

                return is_array($decoded) && $decoded !== [] ? $decoded : null;
            },
            set: function (mixed $value): ?string {
                if (! is_array($value) || $value === []) {
                    return null;
                }

                $rows = array_values($value);
                $hours = is_array($rows[0] ?? null) ? OfficeHours::fromRows($rows) : $value;

                return $hours === null || $hours === [] ? null : (string) json_encode($hours, JSON_UNESCAPED_UNICODE);
            },
        );
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
     * Facilities with any urgent-care service (docs/urgent-care.md), or with
     * the one named: ed | ems | clinic | dental.
     *
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public function scopeUrgentCare(Builder $query, ?string $service = null): Builder
    {
        return self::whereUrgentCare($query, $service);
    }

    /**
     * The urgent-care filter as a plain static (Filament filters call it on
     * a generic builder). An emergency department counts when confirmed or
     * likely (a public general/clinical hospital awaiting staff).
     *
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public static function whereUrgentCare(Builder $query, ?string $service = null): Builder
    {
        $services = $service !== null && isset(self::URGENT_CARE_SERVICES[$service])
            ? [$service]
            : array_keys(self::URGENT_CARE_SERVICES);

        return $query->where(function (Builder $inner) use ($services): void {
            foreach ($services as $name) {
                $inner->orWhere(self::URGENT_CARE_SERVICES[$name], true);

                if ($name === 'ed') {
                    $inner->orWhere('emergency_department_status', self::ED_UNCONFIRMED_LIKELY);
                }
            }
        });
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

    /**
     * Public free-text search for clinical facilities: the name, or the name of
     * a published department, so "кардиологија" finds hospitals that have one.
     *
     * `id IN (name matches UNION members of the matching departments)`, for the
     * same reason as Doctor::scopeSearchNameOrSpecialty(): an OR with a
     * correlated EXISTS keeps the name trigram index out of the plan.
     *
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public function scopeSearchNameOrDepartment(Builder $query, string $term): Builder
    {
        $departmentIds = Department::query()->published()->searchName($term)->pluck('id');

        $matches = ScriptInsensitiveSearch::whereColumnMatches(
            static::query()->withoutGlobalScopes()->select('facilities.id'),
            'facilities.name',
            $term,
        )->toBase();

        if ($departmentIds->isNotEmpty()) {
            $matches->union(
                DB::table('department_facility')->select('facility_id')->whereIn('department_id', $departmentIds),
            );
        }

        return $query->whereIn('facilities.id', $matches);
    }

    /**
     * Pharmacies are indexed too: unified search filters the clinical and
     * pharmacy verticals on the filterable `type` attribute.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_published
            && ! $this->trashed()
            && $this->type !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['departments' => fn ($relation) => $relation->published()]);
        $departmentNames = $this->departments->pluck('name');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type?->value,
            'city' => $this->city,
            'description' => $this->description,
            // Sortable (config/scout.php); see Doctor::toSearchableArray().
            'is_featured' => (bool) $this->is_featured,
            'department_names' => $departmentNames->all(),
            // Meilisearch does not transliterate; the SQL path matches Latin too.
            'department_names_latin' => $departmentNames
                ->map(fn (string $name): string => MacedonianSearchVariants::cyrillicToLatin($name))
                ->all(),
        ];
    }

    /**
     * Prefixed like Scout's default, so SCOUT_PREFIX moves the data and the
     * index settings (config/scout.php) to the same index.
     */
    public function searchableAs(): string
    {
        return config('scout.prefix').'facilities';
    }
}
