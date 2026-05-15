<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriageSessionAnswer extends Model
{
    protected $fillable = [
        'triage_session_id',
        'step_key',
        'values',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TriageSession::class, 'triage_session_id');
    }
}
