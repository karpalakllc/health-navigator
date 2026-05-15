<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TriageSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'triage_flow_id',
        'user_id',
        'terms_accepted_at',
        'emergency_stopped',
        'outcome_code',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'terms_accepted_at' => 'datetime',
            'emergency_stopped' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(TriageFlow::class, 'triage_flow_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TriageSessionAnswer::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function answersMap(): array
    {
        $map = [];

        foreach ($this->answers as $answer) {
            $map[$answer->step_key] = $answer->values;
        }

        return $map;
    }
}
