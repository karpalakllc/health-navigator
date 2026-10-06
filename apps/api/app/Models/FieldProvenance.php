<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who set a directory field, and whether staff locked it against imports.
 *
 * `value` is what the source last wrote (a scalar as a string, a relation as
 * its sorted ids joined by commas). When the record's current value differs,
 * someone else changed it after the import, and the next import raises a
 * conflict instead of overwriting.
 */
class FieldProvenance extends Model
{
    public const SUBJECT_DOCTOR = 'doctor';

    public const SUBJECT_FACILITY = 'facility';

    protected $table = 'field_provenance';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'field',
        'source',
        'source_record_id',
        'source_url',
        'value',
        'observed_at',
        'locked',
        'locked_by_id',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_id');
    }
}
