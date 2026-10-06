<?php

namespace App\Providers;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Slow-query logging (config/zdravje.php `observability`).
 *
 * - A single query slower than `slow_query_ms` logs a warning with its SQL,
 *   duration and connection. Bindings are left out: they are user input
 *   (search terms, emails) and the statement shape is what an index fix needs.
 * - A request or queued job whose queries add up to more than
 *   `slow_request_queries_ms` logs once — the N+1 case, where no single query
 *   is slow. The framework resets the total per queued job; each HTTP request
 *   is a fresh process under PHP-FPM.
 *
 * The request ID (AssignRequestId) is in every record's context, which ties a
 * slow query to the request that ran it. Kept apart from AppServiceProvider so
 * observability changes do not collide with feature work there.
 */
class ObservabilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $perQuery = (int) config('zdravje.observability.slow_query_ms', 0);
        $cumulative = (int) config('zdravje.observability.slow_request_queries_ms', 0);

        if ($perQuery > 0) {
            DB::listen(static function (QueryExecuted $query) use ($perQuery): void {
                if ($query->time >= $perQuery) {
                    Log::warning('Slow query.', [
                        'sql' => $query->sql,
                        'time_ms' => round($query->time, 1),
                        'connection' => $query->connectionName,
                    ]);
                }
            });
        }

        if ($cumulative > 0) {
            DB::whenQueryingForLongerThan($cumulative, static function (Connection $connection, QueryExecuted $last) use ($cumulative): void {
                Log::warning('Queries in one request or job exceeded the budget.', [
                    'total_ms' => round($connection->totalQueryDuration(), 1),
                    'budget_ms' => $cumulative,
                    'connection' => $connection->getName(),
                    'last_sql' => $last->sql,
                ]);
            });
        }
    }
}
