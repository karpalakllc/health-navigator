<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One of the (up to three) flows a v2 session runs; deleted with the session. */
class TriageSessionFlow extends Model
{
    protected $fillable = [
        'triage_session_id',
        'triage_flow_version_id',
        'flow_key',
        'position',
        'outcome_id',
        'outcome_level',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TriageSession::class, 'triage_session_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(TriageFlowVersion::class, 'triage_flow_version_id');
    }
}
