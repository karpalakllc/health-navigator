<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriageStepOption extends Model
{
    protected $fillable = [
        'triage_step_id',
        'value',
        'label',
        'sort_order',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(TriageStep::class, 'triage_step_id');
    }
}
