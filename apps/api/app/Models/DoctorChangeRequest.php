<?php

namespace App\Models;

use App\Enums\DoctorChangeRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A linked doctor's request to change sensitive profile fields
 * (App\Support\DoctorAccount\DoctorProfileFields). `changes` is the
 * field-level diff {field: {old, new}} taken when it was submitted; the
 * profile keeps its values until staff approve.
 *
 * @property DoctorChangeRequestStatus $status
 * @property array<string, array{old: mixed, new: mixed}> $changes
 */
class DoctorChangeRequest extends Model
{
    use LogsActivity;

    protected $fillable = [
        'doctor_id',
        'user_id',
        'changes',
        'message',
        'status',
        'reviewed_by_id',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'status' => DoctorChangeRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The submission and every decision, with who made it. The diff itself is
     * already on the row (kept as long as the request), so it is not copied.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('doctor_accounts')
            ->logOnly(['doctor_id', 'status', 'reviewed_by_id', 'rejection_reason'])
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
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * @param  Builder<DoctorChangeRequest>  $query
     * @return Builder<DoctorChangeRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DoctorChangeRequestStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === DoctorChangeRequestStatus::Pending;
    }
}
