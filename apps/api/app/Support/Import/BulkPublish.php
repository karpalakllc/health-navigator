<?php

namespace App\Support\Import;

use App\Enums\BulkOperationStatus;
use App\Enums\ImportReviewKind;
use App\Jobs\RunBulkPublish;
use App\Models\BulkOperation;
use App\Models\ImportReviewItem;
use App\Models\User;
use App\Support\Verification\Engine\VerifiedDraftPublisher;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Throwable;

/**
 * A bulk publish of the import review queue — „Објави ги сите
 * верификувани“ (9,000+ drafts), „Објави ги и неверификуваните од ФЗОМ“, a
 * large table selection, or `import:publish` — in chunks, outside any single
 * web request (one request ran out of its 30 seconds after ~3,000).
 *
 * - A bulk_operations row records the set, the snapshot the confirming
 *   modal counted (`up_to_id`: nothing raised later is published), the
 *   cursor (last item processed) and the counts. Re-running a step continues
 *   from the cursor; an item already published is claimed and skipped
 *   (ImportReviewActions::publish), so a step run twice is harmless.
 * - One bulk publish at a time: starting takes a cache lock and refuses
 *   while another is unfinished (running, or stopped on an error and waiting
 *   to be resumed or cancelled). Each step holds a lock of its own, so a
 *   queue worker, the review page and the CLI never process the same
 *   operation at once.
 * - Who drives it: a queue worker (RunBulkPublish, re-dispatching itself
 *   every ~45 s) when the queue is real; the review page, a chunk per poll
 *   within a time budget, when the queue is `sync`/`null` (local previews,
 *   tests) — a sync job would run in the request again — or when a queued
 *   operation has made no progress for STALE_SECONDS (no worker running).
 *   `php artisan import:publish` drives it in the terminal.
 * - When it finishes, the staff member who started it gets a database
 *   notification (the panel's bell) with the counts and any failures.
 */
final class BulkPublish
{
    public const TYPE_VERIFIED = 'verified';

    public const TYPE_FZOM_UNVERIFIED = 'fzom-unverified';

    public const TYPE_SELECTED = 'selected';

    public const TYPES = [self::TYPE_VERIFIED, self::TYPE_FZOM_UNVERIFIED, self::TYPE_SELECTED];

    /** Items per step (one poll of the review page, one loop of the worker). */
    public const CHUNK = 500;

    /** Items per sub-batch: progress is saved and the budget checked after each. */
    public const BATCH = 50;

    /** A table selection larger than this is published in the background. */
    public const SELECTION_INLINE_LIMIT = 100;

    /** A queued operation without progress this long is continued by the review page. */
    public const STALE_SECONDS = 90;

    /** Longest a step may hold its lock; a killed step frees it after this. */
    private const STEP_LOCK_SECONDS = 300;

    /**
     * @param  int  $chunk  items per step
     * @param  int  $selectionInlineLimit  a larger table selection is published in the background
     * @param  int  $batch  items per sub-batch
     */
    public function __construct(
        private readonly VerifiedDraftPublisher $publisher,
        public readonly int $chunk = self::CHUNK,
        public readonly int $selectionInlineLimit = self::SELECTION_INLINE_LIMIT,
        private readonly int $batch = self::BATCH,
    ) {}

    /**
     * True when queued jobs would run inside the current request (sync) or
     * never (null): the review page drives the operation instead.
     */
    public static function queueRunsInline(): bool
    {
        $connection = config('queue.default');

        return in_array(config("queue.connections.{$connection}.driver"), ['sync', 'null'], true);
    }

    /**
     * Seconds of publishing one web request may spend: half its
     * max_execution_time, at most 15 (the rest is the request around it).
     */
    public static function requestBudget(): float
    {
        $limit = (int) ini_get('max_execution_time');

        return $limit > 0 ? min(15.0, $limit / 2) : 15.0;
    }

    /**
     * Starts a bulk publish and, with a real queue, dispatches its job.
     *
     * @param  int|null  $upToId  the highest item id the confirming modal counted
     * @param  list<int>|null  $itemIds  the selection, for TYPE_SELECTED
     *
     * @throws BulkPublishAlreadyRunning
     */
    public function start(string $type, ?User $by, ?int $upToId, ?array $itemIds = null, ?string $driver = null): BulkOperation
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown bulk publish „{$type}“.");
        }

        $driver ??= self::queueRunsInline() ? BulkOperation::DRIVER_INLINE : BulkOperation::DRIVER_QUEUE;

        $operation = Cache::lock('import:bulk-publish', 10)->block(5, function () use ($type, $by, $upToId, $itemIds, $driver): BulkOperation {
            $unfinished = $this->unfinished();

            if ($unfinished !== null) {
                throw new BulkPublishAlreadyRunning($unfinished);
            }

            if ($itemIds !== null) {
                $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
                sort($itemIds);
            }

            $operation = new BulkOperation([
                'type' => $type,
                'status' => BulkOperationStatus::Running,
                'driver' => $driver,
                'up_to_id' => $upToId,
                'item_ids' => $itemIds,
                'cursor' => 0,
                'processed' => 0,
                'published' => 0,
                'skipped' => 0,
                'failed' => 0,
                'started_by_id' => $by?->getKey(),
                'started_at' => now(),
                'heartbeat_at' => now(),
            ]);
            $operation->total = $this->countRemaining($operation);
            $operation->save();

            return $operation;
        });

        if ($driver === BulkOperation::DRIVER_QUEUE) {
            RunBulkPublish::dispatch($operation->getKey());
        }

        return $operation;
    }

    /**
     * The operation not yet finished (running, or failed and resumable), if any.
     */
    public function unfinished(): ?BulkOperation
    {
        return BulkOperation::query()->unfinished()->latest('id')->first();
    }

    /**
     * Continues a failed operation from its cursor (or nudges a running one
     * nobody is driving). With a real queue its job is dispatched again.
     */
    public function resume(BulkOperation $operation, ?string $driver = null): BulkOperation
    {
        if ($operation->isFinished()) {
            return $operation;
        }

        $driver ??= self::queueRunsInline() ? BulkOperation::DRIVER_INLINE : BulkOperation::DRIVER_QUEUE;
        $operation->forceFill(['status' => BulkOperationStatus::Running, 'driver' => $driver, 'error' => null, 'heartbeat_at' => now()])->save();

        if ($driver === BulkOperation::DRIVER_QUEUE) {
            RunBulkPublish::dispatch($operation->getKey());
        }

        return $operation;
    }

    /**
     * Stops it; what was published stays published.
     */
    public function cancel(BulkOperation $operation): void
    {
        if (! $operation->isFinished()) {
            $operation->forceFill(['status' => BulkOperationStatus::Cancelled, 'finished_at' => now()])->save();
        }
    }

    /**
     * Should the review page process a step on this poll? Inline operations
     * always; queued ones only once they stalled (no worker running).
     */
    public function needsDriving(BulkOperation $operation): bool
    {
        return $operation->isRunning() && ($operation->driver === BulkOperation::DRIVER_INLINE
            || $operation->heartbeat_at === null
            || $operation->heartbeat_at->lt(now()->subSeconds(self::STALE_SECONDS)));
    }

    /**
     * Processes up to CHUNK items from the cursor, stopping early once the
     * time budget is spent (saving progress after every sub-batch). Returns
     * the operation fresh; it is completed (and its starter notified) when
     * nothing is left. Does nothing when another step holds the lock.
     */
    public function step(BulkOperation $operation, float $budgetSeconds = 15.0, ?int $chunk = null): BulkOperation
    {
        $lock = Cache::lock('import:bulk-publish:'.$operation->getKey(), self::STEP_LOCK_SECONDS);

        if (! $lock->get()) {
            return $operation->refresh();
        }

        try {
            $operation->refresh();

            if (! $operation->isRunning()) {
                return $operation;
            }

            $deadline = microtime(true) + $budgetSeconds;
            $by = $operation->startedBy;
            $ids = $this->nextIds($operation, $chunk ?? $this->chunk);

            if ($ids === []) {
                return $this->complete($operation);
            }

            foreach (array_chunk($ids, $this->batch) as $batch) {
                // Cancelled from the page meanwhile: stop, and keep it so.
                if (BulkOperation::query()->whereKey($operation->getKey())->value('status') !== BulkOperationStatus::Running) {
                    return $operation->refresh();
                }

                $result = $this->publisher->publishChunk($batch, $by);

                $operation->forceFill([
                    'cursor' => max($batch),
                    'processed' => $operation->processed + count($batch),
                    'published' => $operation->published + $result['published'],
                    'skipped' => $operation->skipped + $result['skipped'],
                    'failed' => $operation->failed + count($result['failures']),
                    // The first 50 are enough to look into; the count has the rest.
                    'failures' => $result['failures'] === [] ? $operation->failures : array_slice([...($operation->failures ?? []), ...$result['failures']], 0, 50),
                    'heartbeat_at' => now(),
                ])->save();

                if (microtime(true) >= $deadline) {
                    break;
                }
            }

            if ($this->nextIds($operation, 1) === []) {
                return $this->complete($operation);
            }

            return $operation;
        } finally {
            $lock->release();
        }
    }

    /**
     * A step for the review page and the CLI: an error outside the per-item
     * handling stops the operation (Failed, resumable) instead of failing
     * every following poll the same way.
     */
    public function drive(BulkOperation $operation, float $budgetSeconds): BulkOperation
    {
        try {
            return $this->step($operation, $budgetSeconds);
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($operation, class_basename($exception).': '.$exception->getMessage());

            return $operation->refresh();
        }
    }

    /**
     * Stopped on an error the per-item handling did not catch (the
     * database went away…): resumable from its cursor.
     */
    public function fail(BulkOperation $operation, string $error): void
    {
        $operation->refresh();

        if (! $operation->isRunning()) {
            return;
        }

        $operation->forceFill(['status' => BulkOperationStatus::Failed, 'error' => mb_substr($error, 0, 500), 'heartbeat_at' => now()])->save();

        $this->notifyStarter($operation, Notification::make()
            ->title('Објавувањето застана')
            ->body(sprintf('%s: објавени %d од %d. Продолжете го од Import review („Продолжи“)%s.',
                self::label($operation->type), $operation->published, $operation->total,
                $operation->type === self::TYPE_SELECTED ? '' : ' или со php artisan import:publish '.$operation->type))
            ->danger());
    }

    /**
     * The next item ids to process, from the cursor. A selection is walked
     * as stored (publishChunk skips what is no longer an open "new" item);
     * the two sets are re-queried, so an item that left the set since the
     * modal (now blocked by a conflict, published by hand…) is not touched.
     *
     * @return list<int>
     */
    public function nextIds(BulkOperation $operation, int $limit): array
    {
        if ($operation->type === self::TYPE_SELECTED) {
            return array_slice(array_values(array_filter($operation->item_ids ?? [], fn (int $id): bool => $id > $operation->cursor)), 0, $limit);
        }

        return $this->remaining($operation)->limit($limit)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function countRemaining(BulkOperation $operation): int
    {
        if ($operation->type === self::TYPE_SELECTED) {
            return collect(array_chunk($this->nextIds($operation, PHP_INT_MAX), self::CHUNK))
                ->sum(fn (array $ids): int => ImportReviewItem::query()->open()->where('kind', ImportReviewKind::New)->whereKey($ids)->count());
        }

        return $this->remaining($operation)->count();
    }

    /**
     * Items of a set still to process: past the cursor, within the snapshot
     * the confirming modal counted, and still waiting to be published.
     *
     * @return Builder<ImportReviewItem>
     */
    private function remaining(BulkOperation $operation): Builder
    {
        $query = match ($operation->type) {
            self::TYPE_VERIFIED => $this->publisher->pending(),
            self::TYPE_FZOM_UNVERIFIED => $this->publisher->pendingFzomUnverified(),
            default => throw new InvalidArgumentException("Unknown bulk publish set „{$operation->type}“."),
        };

        return $query
            ->where('id', '>', $operation->cursor)
            ->when($operation->up_to_id !== null, fn (Builder $query) => $query->where('id', '<=', $operation->up_to_id))
            ->reorder()
            ->orderBy('id');
    }

    private function complete(BulkOperation $operation): BulkOperation
    {
        $operation->forceFill(['status' => BulkOperationStatus::Completed, 'finished_at' => now(), 'heartbeat_at' => now()])->save();

        $notification = Notification::make()->title('Објавувањето заврши')->body(self::summary($operation));
        $this->notifyStarter($operation, $operation->failed > 0 ? $notification->warning() : $notification->success());

        return $operation;
    }

    /**
     * Into the panel's bell, written now: Filament's database notification
     * is a queued one, and the queue may be the very thing not running.
     */
    private function notifyStarter(BulkOperation $operation, Notification $notification): void
    {
        $operation->startedBy?->notifyNow($notification->toDatabase());
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::TYPE_VERIFIED => 'Верификуваните профили',
            self::TYPE_FZOM_UNVERIFIED => 'Неверификуваните од ФЗОМ',
            self::TYPE_SELECTED => 'Избраните профили',
            default => $type,
        };
    }

    public static function summary(BulkOperation $operation): string
    {
        $summary = sprintf('%s: објавени %d од %d.', self::label($operation->type), $operation->published, $operation->total);

        if ($operation->skipped > 0) {
            $summary .= sprintf(' Прескокнати %d (веќе објавени, отстранети на приговор или повеќе не се во групата).', $operation->skipped);
        }

        if ($operation->failed > 0) {
            $summary .= sprintf(' Неуспешни %d (ставки: %s) — проверете ги во Import review.', $operation->failed,
                collect($operation->failures ?? [])->take(10)->pluck('item')->map(fn (int $id): string => '#'.$id)->implode(', '));
        }

        return $summary;
    }
}
