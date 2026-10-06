<?php

namespace App\Models;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in the import review queue. Raised idempotently: while an item
 * with the same (source, kind, item_key) is open, a re-run refreshes it
 * instead of adding another.
 *
 * @property ImportReviewKind $kind
 * @property ImportReviewStatus $status
 * @property array<string, mixed>|null $details
 */
class ImportReviewItem extends Model
{
    protected $fillable = [
        'import_run_id',
        'source',
        'kind',
        'item_key',
        'subject_type',
        'subject_id',
        'title',
        'details',
        'status',
        'resolved_by_id',
        'resolved_at',
        'resolution',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ImportReviewKind::class,
            'status' => ImportReviewStatus::class,
            'details' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function raise(
        string $source,
        ImportReviewKind $kind,
        string $itemKey,
        string $title,
        array $details = [],
        ?Model $subject = null,
        ?int $runId = null,
    ): self {
        $item = static::query()
            ->where('source', $source)
            ->where('kind', $kind)
            ->where('item_key', $itemKey)
            ->where('status', ImportReviewStatus::Open)
            ->first() ?? new self([
                'source' => $source,
                'kind' => $kind,
                'item_key' => $itemKey,
            ]);

        $item->fill([
            'title' => mb_substr($title, 0, 255),
            'details' => $details,
            'subject_type' => $subject !== null ? self::subjectTypeOf($subject) : $item->subject_type,
            'subject_id' => $subject?->getKey() ?? $item->subject_id,
            'import_run_id' => $runId ?? $item->import_run_id,
        ])->save();

        return $item;
    }

    /**
     * Closes open items of a kind for a key, e.g. "missing" once the record is
     * back in the source.
     */
    public static function autoResolve(string $source, ImportReviewKind $kind, string $itemKey, string $resolution): void
    {
        static::query()
            ->where('source', $source)
            ->where('kind', $kind)
            ->where('item_key', $itemKey)
            ->where('status', ImportReviewStatus::Open)
            ->update([
                'status' => ImportReviewStatus::Resolved->value,
                'resolution' => $resolution,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public static function subjectTypeOf(Model $subject): string
    {
        return match (true) {
            $subject instanceof Doctor => FieldProvenance::SUBJECT_DOCTOR,
            $subject instanceof Facility => FieldProvenance::SUBJECT_FACILITY,
            default => class_basename($subject),
        };
    }

    public function subject(): Doctor|Facility|null
    {
        return match ($this->subject_type) {
            FieldProvenance::SUBJECT_DOCTOR => Doctor::withTrashed()->find($this->subject_id),
            FieldProvenance::SUBJECT_FACILITY => Facility::withTrashed()->find($this->subject_id),
            default => null,
        };
    }

    /**
     * Closes the item if it is still open. Returns false when someone (or an
     * earlier action of the same bulk run) closed it first: the caller must
     * then do nothing, so a decision is never applied or counted twice.
     */
    public function resolve(ImportReviewStatus $status, string $resolution, ?User $by): bool
    {
        $now = now();
        $values = [
            'status' => $status->value,
            'resolution' => $resolution,
            'resolved_by_id' => $by?->getKey(),
            'resolved_at' => $now,
            'updated_at' => $now,
        ];

        $claimed = static::query()->whereKey($this->getKey())->open()->update($values) === 1;
        $this->refresh();

        return $claimed;
    }

    /**
     * @param  Builder<ImportReviewItem>  $query
     * @return Builder<ImportReviewItem>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ImportReviewStatus::Open);
    }

    /**
     * @return BelongsTo<ImportRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
