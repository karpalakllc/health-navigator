<?php

namespace Tests\Feature\Console;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Tests\Support\FakeMeilisearchEngine;
use Tests\TestCase;

class SearchReindexCommandTest extends TestCase
{
    use RefreshDatabase;

    private FakeMeilisearchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = FakeMeilisearchEngine::install();
    }

    public function test_it_syncs_settings_and_imports_every_model(): void
    {
        Doctor::factory()->create();

        $this->artisan('search:reindex')
            ->expectsOutputToContain('Search indexes rebuilt.')
            ->assertSuccessful();

        $this->assertSame(['doctors', 'facilities', 'forum_topics'], array_keys($this->engine->indexSettings));
        $this->assertSame(['city'], $this->engine->indexSettings['doctors']['filterableAttributes']);
    }

    public function test_a_failed_settings_sync_fails_the_command(): void
    {
        $this->engine->failIndexSettingsWith = 'Meilisearch is unreachable';

        $this->artisan('search:reindex')
            ->expectsOutputToContain('Search reindex failed: Meilisearch is unreachable')
            ->doesntExpectOutputToContain('Search indexes rebuilt.')
            ->assertFailed();
    }

    public function test_scout_prefix_puts_settings_and_documents_on_the_same_index(): void
    {
        config(['scout.prefix' => 'staging_']);
        $doctor = Doctor::factory()->create(['full_name' => 'Ana Prefixed']);

        $this->artisan('search:reindex')->assertSuccessful();

        foreach ([Doctor::class, Facility::class, ForumTopic::class] as $model) {
            $index = (new $model)->searchableAs();

            $this->assertStringStartsWith('staging_', $index);
            $this->assertArrayHasKey($index, $this->engine->indexSettings, "No settings synced to [{$index}].");
        }

        $this->assertArrayHasKey($doctor->id, $this->engine->indexes['staging_doctors'] ?? []);

        $this->getJson('/api/v1/search?q=ana+prefixed')
            ->assertOk()
            ->assertJsonPath('data.doctors.meta.total', 1);
    }

    public function test_meilisearch_failures_are_reported_when_search_falls_back_to_sql(): void
    {
        Exceptions::fake();
        $this->engine->failSearchesWith = 'invalid filter';
        Doctor::factory()->create(['full_name' => 'Ana Fallback']);

        $this->getJson('/api/v1/search?q=ana+fallback')
            ->assertOk()
            ->assertJsonPath('data.doctors.meta.total', 1);

        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'invalid filter');

        Exceptions::fake();
        Cache::put('meilisearch.health', true, 300);
        $this->getJson('/api/v1/forum/topics?q=ana')->assertOk();

        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'invalid filter');
    }
}
