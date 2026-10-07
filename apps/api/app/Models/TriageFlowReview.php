<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A clinician's review of one flow version, recorded by a staff member. The
 * reviewer's name and registration number are optional (the clinician may
 * prefer not to be named); the date and the note are not.
 */
class TriageFlowReview extends Model
{
    public const DECISION_APPROVED = 'approved';

    public const DECISION_CHANGES_REQUESTED = 'changes_requested';

    protected $fillable = [
        'triage_flow_version_id',
        'decision',
        'reviewer_name',
        'reviewer_registration',
        'reviewed_on',
        'note',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_on' => 'date',
        ];
    }

    /** @return BelongsTo<TriageFlowVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(TriageFlowVersion::class, 'triage_flow_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
