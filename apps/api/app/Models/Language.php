<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesTaxonomyCache;
use App\Support\ScriptInsensitiveSearch;
use App\Support\TaxonomyCache;
use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory, InvalidatesTaxonomyCache, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
        'is_published',
    ];

    /**
     * @return list<string>
     */
    public static function taxonomyCacheGroups(): array
    {
        return [TaxonomyCache::LANGUAGES];
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Doctor, $this>
     */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_language')->withTimestamps();
    }

    /**
     * @param  Builder<Language>  $query
     * @return Builder<Language>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<Language>  $query
     * @return Builder<Language>
     */
    public function scopeSearchName(Builder $query, string $term): Builder
    {
        return ScriptInsensitiveSearch::whereColumnMatches($query, 'name', $term);
    }
}
