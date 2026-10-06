<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The last accepted, minimised payload of one external record (a ФЗОМ
 * facility or doctor), keyed by source and external key. `hash` lets a
 * re-run skip records that did not change.
 *
 * @property array<string, mixed> $payload
 */
class SourceRecord extends Model
{
    protected $fillable = [
        'source',
        'external_key',
        'subject_type',
        'subject_id',
        'payload',
        'hash',
        'first_seen_at',
        'last_seen_at',
        'last_run_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
