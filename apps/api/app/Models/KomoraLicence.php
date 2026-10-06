<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One licence of the Лекарска комора list, as last published, and what the
 * matcher did with it (outcome). Staging only: the doctor profile gets its
 * licence through the DoctorLicenceSink, and rows the matcher could not
 * attach are in the import review queue. See the 2026_10_15_110001 migration.
 *
 * @property string $licence_number
 * @property string $full_name
 * @property string $name_key
 * @property string|null $specialty
 * @property string|null $specialty_key
 * @property Carbon|null $valid_until
 * @property Carbon $list_date
 * @property string|null $source_reference
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $missing_since
 * @property string|null $outcome
 * @property int|null $doctor_id
 * @property list<int>|null $candidate_doctor_ids
 * @property Carbon|null $matched_at
 */
class KomoraLicence extends Model
{
    /** @var array<string, string> what the matcher did, for the admin list */
    public const OUTCOMES = [
        'attached' => 'Attached',
        'unchanged' => 'Unchanged',
        'locked' => 'Locked by staff',
        'conflict' => 'Conflict',
        'doctor_not_found' => 'Doctor not found',
        'ambiguous' => 'Ambiguous',
        'no_match' => 'No match',
        'specialty_mismatch' => 'Specialty mismatch',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'list_date' => 'date',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'missing_since' => 'date',
            'candidate_doctor_ids' => 'array',
            'matched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function isExpired(?Carbon $today = null): bool
    {
        return $this->valid_until !== null && $this->valid_until->lt(($today ?? now())->startOfDay());
    }

    /**
     * @param  Builder<KomoraLicence>  $query
     * @return Builder<KomoraLicence>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('valid_until')->whereDate('valid_until', '<', now()->toDateString());
    }
}
