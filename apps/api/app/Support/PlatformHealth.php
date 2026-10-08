<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class PlatformHealth
{
    /**
     * Pending jobs older than this many minutes mean the worker is down or
     * badly behind — treat readiness as degraded.
     */
    public const QUEUE_STALE_MINUTES = 5;

    /**
     * A reserved job held longer than this is stuck (worker crash mid-job).
     */
    public const QUEUE_RESERVED_STALE_MINUTES = 15;

    /**
     * @return array{healthy: bool, checks: array<string, string>}
     */
    public static function check(): array
    {
        $checks = [
            'app' => 'ok',
        ];

        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (Throwable) {
            $checks['database'] = 'error';
        }

        if (config('cache.default') === 'redis') {
            $checks['redis'] = self::checkRedis();
        }

        $queue = (string) config('queue.default');

        if (! in_array($queue, ['sync', 'null'], true)) {
            $checks['queue'] = self::checkQueue($queue);
        }

        $healthy = ! in_array('error', $checks, true);

        return [
            'healthy' => $healthy,
            'checks' => $checks,
        ];
    }

    private static function checkRedis(): string
    {
        try {
            $key = 'platform_health_'.uniqid('', true);
            Cache::store('redis')->put($key, '1', 10);

            return Cache::store('redis')->get($key) === '1' ? 'ok' : 'error';
        } catch (Throwable) {
            return 'error';
        }
    }

    private static function checkQueue(string $driver): string
    {
        try {
            return match ($driver) {
                'database' => self::checkDatabaseQueue(),
                'redis' => self::checkRedisQueue(),
                default => 'ok',
            };
        } catch (Throwable) {
            return 'error';
        }
    }

    private static function checkDatabaseQueue(): string
    {
        $table = (string) config('queue.connections.database.table', 'jobs');
        $staleBefore = now()->subMinutes(self::QUEUE_STALE_MINUTES)->getTimestamp();
        $reservedBefore = now()->subMinutes(self::QUEUE_RESERVED_STALE_MINUTES)->getTimestamp();

        $stalePending = DB::table($table)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $staleBefore)
            ->exists();

        $stuckReserved = DB::table($table)
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<=', $reservedBefore)
            ->exists();

        return ($stalePending || $stuckReserved) ? 'error' : 'ok';
    }

    private static function checkRedisQueue(): string
    {
        $connection = (string) config('queue.connections.redis.connection', 'default');
        // A ping proves the broker the worker reads is reachable. Depth/lag
        // for Redis queues is left to the worker supervisor and failed-job alerts.
        Redis::connection($connection)->ping();

        return 'ok';
    }
}
