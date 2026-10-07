<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deletes expired rows from every database-backed cache store.
 *
 * Laravel's database store removes an expired row only when the same key is
 * read again, so the rate-limiter key of a visitor who never comes back would
 * stay in the table for good. Run hourly, this bounds how long any limiter
 * key outlives its window (docs/data-inventory.md). Other cache drivers expire
 * keys themselves and are skipped.
 */
class PurgeExpiredCacheCommand extends Command
{
    protected $signature = 'cache:purge-expired';

    protected $description = 'Delete expired rows from database cache stores (rate-limiter keys included)';

    public function handle(): int
    {
        $now = now()->getTimestamp();
        $done = [];

        /** @var array<string, array<string, mixed>> $stores */
        $stores = config('cache.stores', []);

        foreach ($stores as $store) {
            if (($store['driver'] ?? null) !== 'database') {
                continue;
            }

            $connection = isset($store['connection']) && is_string($store['connection']) ? $store['connection'] : null;
            $table = is_string($store['table'] ?? null) ? $store['table'] : 'cache';
            $lockTable = is_string($store['lock_table'] ?? null) && $store['lock_table'] !== '' ? $store['lock_table'] : 'cache_locks';

            foreach ([$table, $lockTable] as $name) {
                $id = ($connection ?? '').'|'.$name;

                if (isset($done[$id]) || ! Schema::connection($connection)->hasTable($name)) {
                    continue;
                }

                $done[$id] = true;
                // DatabaseStore treats a row as expired from its expiration second on.
                $deleted = DB::connection($connection)->table($name)->where('expiration', '<=', $now)->delete();
                $this->info("Deleted {$deleted} expired row(s) from {$name}.");
            }
        }

        return self::SUCCESS;
    }
}
