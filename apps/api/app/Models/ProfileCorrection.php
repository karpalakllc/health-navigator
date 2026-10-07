<?php

namespace App\Models;

use App\Enums\FacilityType;
use App\Enums\ProfileCorrectionField;
use App\Enums\ProfileCorrectionStatus;
use App\Enums\ProfileCorrectionType;
use App\Enums\ProfileReportReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A public request about a doctor or facility profile: a correction („Пријави
 * грешка во профилот“) or the listed doctor's objection / removal request.
 * Staff fix the profile in its own edit page and close the request here with
 * a note; nothing on the profile changes by itself.
 *
 * A profile report („Пријави профил“, type report, W7-C) is a third kind:
 * a reason code and an optional note. Reports about one profile are decided
 * together, and a profile with several independent open reports is sorted to
 * the top of the queue (scopePrioritised); nothing is hidden automatically.
 *
 * Closed requests are deleted zdravje.corrections.retention_days after they
 * were closed, closed reports zdravje.profile_reports.retention_days
 * (model:prune, daily). Open ones are never pruned.
 *
 * @property ProfileCorrectionType $type
 * @property ProfileCorrectionStatus $status
 * @property ProfileCorrectionField|null $field
 * @property ProfileReportReason|null $report_reason
 * @property Carbon $due_at
 * @property Carbon|null $resolved_at
 */
class ProfileCorrection extends Model
{
    use MassPrunable;

    public const MESSAGE_MAX_LENGTH = 1000;

    public const CONTACT_MAX_LENGTH = 255;

    public const NOTE_MAX_LENGTH = 2000;

    /**
     * Profiles a request can be about. Keys are the short names the admin
     * panel shows; morph types stay class names, as on reviews.
     *
     * @var array<string, class-string<Model>>
     */
    public const SUBJECT_TYPES = [
        'doctor' => Doctor::class,
        'facility' => Facility::class,
    ];

    protected $fillable = [
        'type',
        'subject_type',
        'subject_id',
        'field',
        'report_reason',
        'message',
        'contact',
        'user_id',
        'reporter_hash',
        'status',
        'due_at',
        'resolved_by_id',
        'resolved_at',
        'resolution_note',
        'staff_alerted_at',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProfileCorrectionType::class,
            'status' => ProfileCorrectionStatus::class,
            'field' => ProfileCorrectionField::class,
            'report_reason' => ProfileReportReason::class,
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'staff_alerted_at' => 'datetime',
        ];
    }

    /**
     * The profile, also when it has since been deleted (an upheld objection).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed();
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
     * @param  Builder<ProfileCorrection>  $query
     * @return Builder<ProfileCorrection>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ProfileCorrectionStatus::Open);
    }

    /**
     * @param  Builder<ProfileCorrection>  $query
     * @return Builder<ProfileCorrection>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->where('due_at', '<', now());
    }

    /**
     * Adds `open_report_count`: independent open profile reports about the
     * same profile (each account once, each guest address once — the hash is
     * per profile — and a report from neither on its own).
     *
     * @param  Builder<ProfileCorrection>  $query
     * @return Builder<ProfileCorrection>
     */
    public function scopeWithOpenReportCount(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        return $query->selectSub(self::openReportCountQuery($this->getTable()), 'open_report_count');
    }

    /**
     * Profiles with zdravje.profile_reports.priority_threshold or more
     * independent open reports first, the most reported first; everything
     * else keeps whatever order follows.
     *
     * @param  Builder<ProfileCorrection>  $query
     * @return Builder<ProfileCorrection>
     */
    public function scopePrioritised(Builder $query): Builder
    {
        $count = self::openReportCountQuery($this->getTable());
        $sql = $count->toSql();
        $threshold = (int) config('zdravje.profile_reports.priority_threshold', 3);

        return $query->orderByRaw(
            "(case when ({$sql}) >= ? then ({$sql}) else 0 end) desc",
            [...$count->getBindings(), $threshold, ...$count->getBindings()],
        );
    }

    /**
     * Only requests about a profile at or over the priority threshold.
     *
     * @param  Builder<ProfileCorrection>  $query
     * @return Builder<ProfileCorrection>
     */
    public function scopeOnPriorityProfiles(Builder $query): Builder
    {
        $count = self::openReportCountQuery($this->getTable());

        return $query->whereRaw(
            "({$count->toSql()}) >= ?",
            [...$count->getBindings(), (int) config('zdravje.profile_reports.priority_threshold', 3)],
        );
    }

    /**
     * Correlated count of independent open reports on the outer row's profile.
     */
    private static function openReportCountQuery(string $outer): QueryBuilder
    {
        return DB::table($outer.' as reports')
            ->selectRaw("count(distinct coalesce('u' || reports.user_id, 'g' || reports.reporter_hash, 'r' || reports.id))")
            ->whereColumn('reports.subject_type', $outer.'.subject_type')
            ->whereColumn('reports.subject_id', $outer.'.subject_id')
            ->where('reports.type', ProfileCorrectionType::Report->value)
            ->where('reports.status', ProfileCorrectionStatus::Open->value);
    }

    /** Independent open reports on this row's profile (see scopeWithOpenReportCount). */
    public function openReportCount(): int
    {
        $loaded = $this->getAttribute('open_report_count');

        if ($loaded !== null) {
            return (int) $loaded;
        }

        return (int) self::query()->withOpenReportCount()->whereKey($this->getKey())->value('open_report_count');
    }

    public function isPriority(): bool
    {
        return $this->openReportCount() >= (int) config('zdravje.profile_reports.priority_threshold', 3);
    }

    public function isOpen(): bool
    {
        return $this->status === ProfileCorrectionStatus::Open;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at->isPast();
    }

    /** The profile's display name, or a placeholder when it is gone for good. */
    public function subjectName(): string
    {
        $subject = $this->subject;

        return match (true) {
            $subject instanceof Doctor => (string) $subject->full_name,
            $subject instanceof Facility => (string) $subject->name,
            default => '(deleted profile)',
        };
    }

    /** „doctor“, „facility“ or „pharmacy“, for the panel. */
    public function subjectKind(): string
    {
        $subject = $this->subject;

        if ($subject instanceof Facility && $subject->type === FacilityType::Pharmacy) {
            return 'pharmacy';
        }

        return array_search($this->subject_type, self::SUBJECT_TYPES, true) ?: 'profile';
    }

    /**
     * Close the request with staff's note. Returns false when it was already
     * closed (two people in the queue at once): the first decision stands.
     *
     * Upholding a doctor's objection removes the profile in the same
     * transaction: it is unpublished (if it still exists) and suppressed, so
     * no import creates or publishes it again (ImportSuppression).
     */
    public function close(ProfileCorrectionStatus $outcome, User $staff, string $note): bool
    {
        if ($outcome === ProfileCorrectionStatus::Open) {
            return false;
        }

        $closed = DB::transaction(function () use ($outcome, $staff, $note): int {
            // A decision on a profile report decides every open report on
            // that profile: they are about the same thing, and the queue
            // should not hold ten copies of a question already answered.
            $rows = $this->type === ProfileCorrectionType::Report
                ? self::query()
                    ->where('subject_type', $this->subject_type)
                    ->where('subject_id', $this->subject_id)
                    ->where('type', ProfileCorrectionType::Report->value)
                : self::query()->whereKey($this->getKey());

            if ($this->type === ProfileCorrectionType::Report && ! self::query()->whereKey($this->getKey())->open()->exists()) {
                return 0;
            }

            $closed = $rows
                ->open()
                ->update([
                    'status' => $outcome->value,
                    'resolved_by_id' => $staff->getKey(),
                    'resolved_at' => now(),
                    'resolution_note' => $note,
                    // The guest-address hash only told open reports apart.
                    'reporter_hash' => null,
                    'updated_at' => now(),
                ]);

            if ($closed === 1 && $outcome === ProfileCorrectionStatus::Resolved && $this->type === ProfileCorrectionType::Objection) {
                $this->removeObjectedDoctor($staff);
            }

            return $closed;
        });

        if ($closed === 0) {
            $this->refresh();

            return false;
        }

        $this->refresh();

        // Audit log: the decision, by whom, on which profile. Never the
        // message, the contact or the note (they can hold personal data).
        activity('profile_corrections')
            ->causedBy($staff)
            ->performedOn($this)
            ->event($outcome->value)
            ->withProperties([
                'type' => $this->type->value,
                'subject_type' => $this->subject_type,
                'subject_id' => $this->subject_id,
                'requests_closed' => $closed,
            ])
            ->log('profile_correction_closed');

        return true;
    }

    private function removeObjectedDoctor(User $staff): void
    {
        if ($this->subject_type !== Doctor::class) {
            return;
        }

        $doctor = Doctor::withTrashed()->find($this->subject_id);

        if ($doctor === null) {
            // Already deleted for good: its deletion left a suppression.
            ImportSuppression::query()->active()->where('doctor_id', $this->subject_id)
                ->update(['reason' => ImportSuppression::REASON_OBJECTION, 'profile_correction_id' => $this->getKey(), 'updated_at' => now()]);

            return;
        }

        if ($doctor->is_published) {
            $doctor->forceFill(['is_published' => false])->save();
        }

        ImportSuppression::forDoctor($doctor, ImportSuppression::REASON_OBJECTION, $staff, (int) $this->getKey());
    }

    /**
     * Closed requests past the retention period.
     *
     * @return Builder<ProfileCorrection>
     */
    public function prunable(): Builder
    {
        $days = max(30, (int) config('zdravje.corrections.retention_days', 365));
        $reportDays = max(30, (int) config('zdravje.profile_reports.retention_days', 90));

        return self::query()
            ->where('status', '!=', ProfileCorrectionStatus::Open->value)
            ->whereNotNull('resolved_at')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('type', ProfileCorrectionType::Report->value)
                    ->where('resolved_at', '<', now()->subDays($reportDays)))
                ->orWhere(fn (Builder $query) => $query
                    ->where('type', '!=', ProfileCorrectionType::Report->value)
                    ->where('resolved_at', '<', now()->subDays($days))));
    }
}
