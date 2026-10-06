<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * App\Providers\ObservabilityServiceProvider: one slow query, or a request
 * whose queries add up past the budget, logs a warning without bindings.
 * Defaults: 500 ms per query, 2000 ms per request/job.
 */
class SlowQueryLoggingTest extends TestCase
{
    public function test_a_single_slow_query_is_logged_without_its_bindings(): void
    {
        Log::spy();

        DB::connection()->logQuery('select * from users where email = ?', ['someone@example.test'], 750.0);

        Log::shouldHaveReceived('warning')->once()->with('Slow query.', Mockery::on(
            fn (array $context): bool => $context['sql'] === 'select * from users where email = ?'
                && $context['time_ms'] === 750.0
                && ! str_contains((string) json_encode($context), 'someone@example.test'),
        ));
    }

    public function test_fast_queries_are_not_logged(): void
    {
        Log::spy();

        DB::connection()->logQuery('select 1', [], 12.0);

        Log::shouldNotHaveReceived('warning');
    }

    public function test_many_fast_queries_past_the_budget_log_once(): void
    {
        Log::spy();

        foreach (range(1, 30) as $ignored) {
            DB::connection()->logQuery('select * from doctors where id = ?', [1], 100.0);
        }

        Log::shouldNotHaveReceived('warning', ['Slow query.', Mockery::any()]);
        Log::shouldHaveReceived('warning')->once()->with('Queries in one request or job exceeded the budget.', Mockery::on(
            fn (array $context): bool => $context['budget_ms'] === 2000 && $context['total_ms'] > 2000,
        ));
    }
}
