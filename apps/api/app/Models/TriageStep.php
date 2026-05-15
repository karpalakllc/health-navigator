<?php

namespace App\Models;

use App\Enums\TriageStepType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TriageStep extends Model
{
    protected $fillable = [
        'triage_flow_id',
        'step_key',
        'type',
        'label',
        'sort_order',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'type' => TriageStepType::class,
            'is_required' => 'boolean',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(TriageFlow::class, 'triage_flow_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(TriageStepOption::class)->orderBy('sort_order');
    }

    public function allowsValue(string $value): bool
    {
        return $this->options()->where('value', $value)->exists();
    }

    /**
     * @param  list<string>  $values
     */
    public function allowsValues(array $values): bool
    {
        if ($values === []) {
            return ! $this->is_required;
        }

        foreach ($values as $value) {
            if (! $this->allowsValue($value)) {
                return false;
            }
        }

        if ($this->type === TriageStepType::SingleSelect && count($values) > 1) {
            return false;
        }

        return true;
    }
}
