<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriageRule extends Model
{
    protected $fillable = [
        'triage_flow_id',
        'priority',
        'outcome_code',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'priority' => 'integer',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(TriageFlow::class, 'triage_flow_id');
    }
}
