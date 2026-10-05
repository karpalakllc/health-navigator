<?php

namespace App\Models;

use Database\Factories\ForumCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumCategory extends Model
{
    /** @use HasFactory<ForumCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'sort_order',
        'is_published',
        'published_at',
    ];

    protected static function booted(): void
    {
        // Topic visibility in search depends on the category's publication, but
        // toggling it only saves this row — re-sync the topics' index entries.
        static::updated(function (ForumCategory $category): void {
            if (! $category->wasChanged('is_published')) {
                return;
            }

            if ($category->is_published) {
                $category->topics()->searchable();
            } else {
                $category->topics()->unsearchable();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ForumTopic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(ForumTopic::class);
    }

    /**
     * @param  Builder<ForumCategory>  $query
     * @return Builder<ForumCategory>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
