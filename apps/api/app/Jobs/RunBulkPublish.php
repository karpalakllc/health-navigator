<?php

namespace App\Jobs;

use App\Models\BulkOperation;
use App\Support\Import\BulkPublish;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Drives one bulk publish (BulkPublish) on a queue worker: steps of
 * BulkPublish::CHUNK items for up to RUN_SECONDS, then dispatches itself
 * again for the rest — no single job outlives the worker's --timeout, and a
 * restarted worker picks up from the cursor. A step another driver holds
 * (the review page, the CLI) makes this job wait and try again.
 */
final class RunBulkPublish implements ShouldQueue
{
    use Queueable;

    /** Seconds of publishing per job before it hands over to the next one. */
    public const RUN_SECONDS = 45;

    public int $timeout = 120;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $operationId) {}

    public function handle(BulkPublish $bulk): void
    {
        $operation = BulkOperation::query()->find($this->operationId);

        if ($operation === null) {
            return;
        }

        $until = microtime(true) + self::RUN_SECONDS;

        while ($operation->isRunning() && microtime(true) < $until) {
            $before = $operation->processed;
            $operation = $bulk->step($operation, max(1.0, $until - microtime(true)));

            if ($operation->isRunning() && $operation->processed === $before) {
                // Another driver holds the step: let it, and look again
                // later (a fresh job, so waiting never uses up the tries).
                self::dispatch($operation->getKey())->delay(15);

                return;
            }
        }

        if ($operation->isRunning()) {
            self::dispatch($operation->getKey());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $operation = BulkOperation::query()->find($this->operationId);

        if ($operation !== null) {
            app(BulkPublish::class)->fail($operation, $exception !== null ? class_basename($exception).': '.$exception->getMessage() : 'The queue job failed.');
        }
    }
}
