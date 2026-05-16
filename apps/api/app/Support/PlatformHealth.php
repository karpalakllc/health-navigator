<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PlatformHealth
{
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
}
