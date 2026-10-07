<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TriageFlow extends Model
{
    protected $fillable = [
        'key',
        'title',
        'intro_body',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TriageStep::class)->orderBy('sort_order');
    }

    public function redFlags(): HasMany
    {
        return $this->hasMany(TriageRedFlag::class)->orderBy('sort_order');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(TriageRule::class)->orderBy('priority');
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(TriageOutcome::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TriageSession::class);
    }

    /**
     * v2 flows (docs/triage-flows.md): one row per flow file, content in versions.
     *
     * @return HasMany<TriageFlowVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TriageFlowVersion::class)->orderByDesc('version');
    }

    /** @return HasOne<TriageFlowVersion, $this> */
    public function publishedVersion(): HasOne
    {
        return $this->hasOne(TriageFlowVersion::class)
            ->where('status', TriageFlowVersion::STATUS_PUBLISHED);
    }

    /** @return HasOne<TriageFlowVersion, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(TriageFlowVersion::class)->latestOfMany('version');
    }

    public function isV2(): bool
    {
        return $this->key !== null;
    }

    /**
     * @param  Builder<TriageFlow>  $query
     * @return Builder<TriageFlow>
     */
    public function scopeV1(Builder $query): Builder
    {
        return $query->whereNull('key');
    }

    /**
     * @param  Builder<TriageFlow>  $query
     * @return Builder<TriageFlow>
     */
    public function scopeV2(Builder $query): Builder
    {
        return $query->whereNotNull('key');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /** The single published v1 flow; v2 flows never set is_published. */
    public static function publishedFlow(): ?self
    {
        return static::query()->v1()->published()->first();
    }
}
