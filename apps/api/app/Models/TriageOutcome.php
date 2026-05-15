<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriageOutcome extends Model
{
    protected $fillable = [
        'triage_flow_id',
        'code',
        'title',
        'body',
        'handoffs',
    ];

    protected function casts(): array
    {
        return [
            'handoffs' => 'array',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(TriageFlow::class, 'triage_flow_id');
    }
}
