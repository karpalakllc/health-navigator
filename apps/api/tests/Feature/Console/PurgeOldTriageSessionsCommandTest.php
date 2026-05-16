<?php

namespace Tests\Feature\Console;

use App\Models\TriageFlow;
use App\Models\TriageSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurgeOldTriageSessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purges_sessions_older_than_retention_window(): void
    {
        $flow = TriageFlow::query()->create([
            'title' => 'Test flow',
            'is_published' => true,
        ]);

        $old = TriageSession::query()->create([
            'triage_flow_id' => $flow->id,
            'terms_accepted_at' => now()->subDays(120),
        ]);
        $old->forceFill([
            'created_at' => Carbon::now()->subDays(120),
            'updated_at' => Carbon::now()->subDays(120),
        ])->saveQuietly();

        $recent = TriageSession::query()->create([
            'triage_flow_id' => $flow->id,
            'terms_accepted_at' => now()->subDays(10),
        ]);

        $this->artisan('triage:purge-old-sessions', ['--days' => 90])
            ->assertSuccessful();

        $this->assertDatabaseMissing('triage_sessions', ['id' => $old->id]);
        $this->assertDatabaseHas('triage_sessions', ['id' => $recent->id]);
    }

    public function test_dry_run_does_not_delete(): void
    {
        $flow = TriageFlow::query()->create([
            'title' => 'Test flow',
            'is_published' => true,
        ]);

        $old = TriageSession::query()->create([
            'triage_flow_id' => $flow->id,
            'terms_accepted_at' => now()->subDays(120),
        ]);
        $old->forceFill([
            'created_at' => Carbon::now()->subDays(120),
            'updated_at' => Carbon::now()->subDays(120),
        ])->saveQuietly();

        $this->artisan('triage:purge-old-sessions', ['--days' => 90, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('triage_sessions', ['id' => $old->id]);
    }
}
