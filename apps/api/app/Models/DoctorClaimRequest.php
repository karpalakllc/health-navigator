<?php

namespace App\Models;

use App\Enums\DoctorClaimRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * „Ова е мој профил“: a member's request to manage a doctor profile. Staff
 * verify the person outside the platform, then assign the account
 * (App\Actions\DoctorAccount\AssignDoctorOwner) or reject the request.
 *
 * @property DoctorClaimRequestStatus $status
 */
class DoctorClaimRequest extends Model
{
    use LogsActivity;

    public const MESSAGE_MAX_LENGTH = 1000;

    public const CONTACT_MAX_LENGTH = 255;

    /** Only the decision is audited; the message and contact stay out of the log. */
    protected static array $recordEvents = ['updated'];

    protected $fillable = [
        'doctor_id',
        'user_id',
        'message',
        'contact',
        'status',
        'resolved_by_id',
        'resolved_at',
        'resolution_note',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => DoctorClaimRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('doctor_accounts')
            ->logOnly(['status', 'resolved_by_id', 'resolution_note'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    /**
     * @param  Builder<DoctorClaimRequest>  $query
     * @return Builder<DoctorClaimRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DoctorClaimRequestStatus::Pending);
    }
}
