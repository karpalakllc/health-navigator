<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TriageFlow extends Model
{
    protected $fillable = [
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

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public static function publishedFlow(): ?self
    {
        return static::query()->published()->first();
    }
}
