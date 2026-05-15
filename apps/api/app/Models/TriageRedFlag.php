<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriageRedFlag extends Model
{
    protected $fillable = [
        'triage_flow_id',
        'code',
        'label',
        'sort_order',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(TriageFlow::class, 'triage_flow_id');
    }
}
