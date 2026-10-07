<?php

namespace Tests\Feature\Triage;

use App\Models\TriageFlow;
use App\Models\TriageFlowReview;
use App\Models\TriageFlowVersion;
use App\Services\Triage\V2\FlowImporter;
use App\Services\Triage\V2\FlowPublication;
use Database\Seeders\TriageFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FlowImportAndPublicationTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/triage-import-'.uniqid();
        mkdir($this->dir);
        config(['triage.flows_path' => $this->dir]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
        parent::tearDown();
    }

    private function writeExample(?callable $mutate = null): void
    {
        $flow = FlowLinterTest::example();

        if ($mutate) {
            $mutate($flow);
        }

        file_put_contents($this->dir.'/example-sore-throat.json', json_encode($flow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function version(int $number): TriageFlowVersion
    {
        return TriageFlow::query()->where('key', 'example-sore-throat')->firstOrFail()
            ->versions()->where('version', $number)->firstOrFail();
    }

    private function approve(TriageFlowVersion $version): void
    {
        app(FlowPublication::class)->recordReview($version, [
            'decision' => TriageFlowReview::DECISION_APPROVED,
            'reviewer_name' => '',
            'reviewed_on' => '2026-10-07',
            'note' => 'Прегледано.',
        ], null);
    }

    public function test_import_creates_a_draft_and_skips_an_unchanged_file(): void
    {
        $this->writeExample();

        $this->artisan('triage:import')->expectsOutputToContain('imported example-sore-throat.json v1')->assertSuccessful();
        $this->artisan('triage:import')->expectsOutputToContain('unchanged example-sore-throat.json v1')->assertSuccessful();

        // Re-formatting (key order) is not a change.
        $flow = FlowLinterTest::example();
        file_put_contents($this->dir.'/example-sore-throat.json', json_encode(array_reverse($flow, true), JSON_UNESCAPED_UNICODE));
        $this->artisan('triage:import')->expectsOutputToContain('unchanged')->assertSuccessful();

        $version = $this->version(1);
        $this->assertSame(TriageFlowVersion::STATUS_DRAFT, $version->status);
        $this->assertSame(0, $version->lint_errors);
        // v2 flows never use the v1 single-flow switch.
        $this->assertFalse((bool) TriageFlow::query()->where('key', 'example-sore-throat')->value('is_published'));
    }

    public function test_a_file_failing_the_linter_is_not_imported(): void
    {
        $this->writeExample(function (&$f) {
            unset($f['red_flags'][0]['source']);
        });

        $this->artisan('triage:import')->expectsOutputToContain('rejected example-sore-throat.json')->assertFailed();

        $this->assertSame(0, TriageFlowVersion::query()->count());
    }

    public function test_publication_needs_an_approving_clinician_review_of_that_version(): void
    {
        $this->writeExample();
        app(FlowImporter::class)->import();
        $publication = app(FlowPublication::class);
        $version = $this->version(1);

        $this->assertStringContainsString('Record a clinician review', (string) $publication->blocker($version));

        $publication->recordReview($version, [
            'decision' => TriageFlowReview::DECISION_CHANGES_REQUESTED,
            'reviewed_on' => '2026-10-07',
            'note' => 'Додадете прашање за бременост.',
        ], null);
        $this->assertSame(TriageFlowVersion::STATUS_DRAFT, $version->fresh()->status);
        $this->assertNotNull($publication->blocker($version->fresh()));

        try {
            $publication->publish($version->fresh(), null);
            $this->fail('Published without approval.');
        } catch (ValidationException) {
        }

        $this->approve($version->fresh());
        $this->assertSame(TriageFlowVersion::STATUS_REVIEWED, $version->fresh()->status);
        $this->assertNull($version->fresh()->latestReview->reviewer_name);

        $publication->publish($version->fresh(), null);
        $this->assertSame(TriageFlowVersion::STATUS_PUBLISHED, $version->fresh()->status);
    }

    public function test_a_changed_file_is_a_new_draft_and_the_published_version_stays_live_until_replaced(): void
    {
        $this->writeExample();
        app(FlowImporter::class)->import();
        $this->approve($this->version(1));
        app(FlowPublication::class)->publish($this->version(1), null);

        $this->writeExample(function (&$f) {
            $f['title'] = 'Болно грло (пример)';
        });
        $this->artisan('triage:import')->expectsOutputToContain('imported example-sore-throat.json v2');

        $this->assertSame(TriageFlowVersion::STATUS_PUBLISHED, $this->version(1)->status);
        $this->assertSame(TriageFlowVersion::STATUS_DRAFT, $this->version(2)->status);
        $this->getJson('/api/v1/triage/v2/catalog')->assertJsonPath('data.flows.0.title', 'Болки во грлото (пример)');

        $this->approve($this->version(2));
        app(FlowPublication::class)->publish($this->version(2), null);

        $this->assertSame(TriageFlowVersion::STATUS_RETIRED, $this->version(1)->status);
        $this->getJson('/api/v1/triage/v2/catalog')->assertJsonPath('data.flows.0.title', 'Болно грло (пример)');
    }

    public function test_publication_re_lints_the_stored_definition(): void
    {
        $this->writeExample();
        app(FlowImporter::class)->import();
        $version = $this->version(1);
        $this->approve($version);

        $definition = $version->definition;
        $definition['outcomes']['o_pharmacy']['watch_for'] = [];
        $version->update(['definition' => $definition]);

        $this->assertStringContainsString('linter errors', (string) app(FlowPublication::class)->blocker($version->fresh()));
    }

    public function test_the_seeder_publishes_the_grandfathered_general_flow_only(): void
    {
        config(['triage.flows_path' => database_path('data/triage/flows')]);

        $this->seed(TriageFlowSeeder::class);
        $this->seed(TriageFlowSeeder::class);

        $general = TriageFlow::query()->where('key', 'general')->firstOrFail();
        $this->assertSame(1, $general->versions()->count());
        $this->assertSame(TriageFlowVersion::STATUS_PUBLISHED, $general->publishedVersion->status);
        $this->assertNotNull($general->publishedVersion->review_exempt_reason);
        $this->assertSame(
            TriageFlowVersion::query()->where('status', 'published')->count(),
            1,
            'Only the grandfathered flow may publish without a review.',
        );
        $this->getJson('/api/v1/triage/v2/catalog')->assertOk()->assertJsonPath('data.flows.0.key', 'general');
    }
}
