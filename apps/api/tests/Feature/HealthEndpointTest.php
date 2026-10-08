<?php

namespace Tests\Feature;

use App\Support\PlatformHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.app', 'ok')
            ->assertJsonPath('data.checks.database', 'ok');

        // phpunit uses QUEUE_CONNECTION=sync — no queue lag check.
        $this->assertArrayNotHasKey('queue', $response->json('data.checks'));
    }

    public function test_stale_database_queue_jobs_mark_health_degraded(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(PlatformHealth::QUEUE_STALE_MINUTES + 1)->getTimestamp(),
            'created_at' => now()->subMinutes(10)->getTimestamp(),
        ]);

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertJsonPath('data.status', 'degraded')
            ->assertJsonPath('data.checks.queue', 'error');
    }

    public function test_fresh_database_queue_jobs_keep_health_ok(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->getTimestamp(),
            'created_at' => now()->getTimestamp(),
        ]);

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.queue', 'ok');
    }
}
