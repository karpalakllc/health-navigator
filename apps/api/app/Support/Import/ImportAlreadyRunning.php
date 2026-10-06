<?php

namespace App\Support\Import;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Another run of the same source holds its lock (a manual run during a
 * scheduled one, or two at once): this one stops before it writes anything.
 */
final class ImportAlreadyRunning extends RuntimeException
{
    /** Longest a run may hold the lock; a killed run frees it after this. */
    private const LOCK_SECONDS = 2 * 3600;

    public static function lock(string $source): Lock
    {
        $lock = Cache::lock('import:'.$source, self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw new self("An import of „{$source}“ is already running; this run stopped without changing anything. Try again when it has finished.");
        }

        return $lock;
    }
}
