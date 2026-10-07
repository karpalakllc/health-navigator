<?php

namespace App\Models;

use App\Enums\BulkOperationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One background bulk publish of the import review queue (BulkPublish).
 *
 * @property string $type
 * @property BulkOperationStatus $status
 * @property string $driver
 * @property int|null $up_to_id
 * @property list<int>|null $item_ids
 * @property int $cursor
 * @property int $total
 * @property int $processed
 * @property int $published
 * @property int $skipped
 * @property int $failed
 * @property list<array{item: int, error: string}>|null $failures
 * @property string|null $error
 * @property int|null $started_by_id
 * @property Carbon|null $started_at
 * @property Carbon|null $heartbeat_at
 * @property Carbon|null $finished_at
 */
class BulkOperation extends Model
{
    /** Published by a queue worker (RunBulkPublish, continuing itself). */
    public const DRIVER_QUEUE = 'queue';

    /** No real queue (sync/null): the review page continues it, a chunk per poll. */
    public const DRIVER_INLINE = 'inline';

    /** `php artisan import:publish`. */
    public const DRIVER_CLI = 'cli';

    protected $fillable = [
        'type',
        'status',
        'driver',
        'up_to_id',
        'item_ids',
        'cursor',
        'total',
        'processed',
        'published',
        'skipped',
        'failed',
        'failures',
        'error',
        'started_by_id',
        'started_at',
        'heartbeat_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BulkOperationStatus::class,
            'up_to_id' => 'integer',
            'item_ids' => 'array',
            'cursor' => 'integer',
            'total' => 'integer',
            'processed' => 'integer',
            'published' => 'integer',
            'skipped' => 'integer',
            'failed' => 'integer',
            'failures' => 'array',
            'started_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Not finished: running, or stopped on an error and waiting to be
     * resumed (or cancelled). Only one at a time.
     *
     * @param  Builder<BulkOperation>  $query
     * @return Builder<BulkOperation>
     */
    public function scopeUnfinished(Builder $query): Builder
    {
        return $query->whereIn('status', [BulkOperationStatus::Running->value, BulkOperationStatus::Failed->value]);
    }

    public function isRunning(): bool
    {
        return $this->status === BulkOperationStatus::Running;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [BulkOperationStatus::Completed, BulkOperationStatus::Cancelled], true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_id');
    }
}
