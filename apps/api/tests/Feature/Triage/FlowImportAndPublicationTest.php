<?php

namespace Tests\Feature\Triage;

use App\Models\TriageFlow;
use App\Models\TriageFlowReview;
use App\Models\TriageFlowVersion;
use App\Models\User;
use App\Services\Triage\V2\FlowImporter;
use App\Services\Triage\V2\FlowPublication;
use App\Support\DeploymentEnvironment;
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
            'reviewer_name' => 'д-р Тест',
            'reviewer_registration' => 'ЛК-0001',
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
        $this->assertSame('д-р Тест', $version->fresh()->latestReview->reviewer_name);

        $publication->publish($version->fresh(), null);
        $this->assertSame(TriageFlowVersion::STATUS_PUBLISHED, $version->fresh()->status);
    }

    public function test_an_approval_needs_the_clinicians_name_and_registration(): void
    {
        $this->writeExample();
        app(FlowImporter::class)->import();
        $version = $this->version(1);

        foreach ([['reviewer_name' => '', 'reviewer_registration' => 'ЛК-1'], ['reviewer_name' => 'д-р Тест', 'reviewer_registration' => ' ']] as $who) {
            try {
                app(FlowPublication::class)->recordReview($version, $who + [
                    'decision' => TriageFlowReview::DECISION_APPROVED,
                    'reviewed_on' => '2026-10-07',
                    'note' => 'Прегледано.',
                ], null);
                $this->fail('Approval recorded without a named, registered clinician.');
            } catch (ValidationException) {
            }
        }

        $this->assertSame(0, $version->reviews()->count());

        // Requesting changes does not need an identity.
        app(FlowPublication::class)->recordReview($version, [
            'decision' => TriageFlowReview::DECISION_CHANGES_REQUESTED,
            'reviewed_on' => '2026-10-07',
            'note' => 'Додадете прашање.',
        ], null);
        $this->assertSame(1, $version->reviews()->count());
    }

    public function test_the_staff_member_who_recorded_the_approval_cannot_publish_that_version(): void
    {
        $this->writeExample();
        app(FlowImporter::class)->import();
        $version = $this->version(1);
        $recorder = User::factory()->create();
        $other = User::factory()->create();
        $publication = app(FlowPublication::class);

        $publication->recordReview($version, [
            'decision' => TriageFlowReview::DECISION_APPROVED,
            'reviewer_name' => 'д-р Тест',
            'reviewer_registration' => 'ЛК-0001',
            'reviewed_on' => '2026-10-07',
            'note' => 'Прегледано.',
        ], $recorder);

        try {
            $publication->publish($version->fresh(), $recorder);
            $this->fail('The recorder published their own approval.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('cannot publish the same version', $e->errors()['version'][0]);
        }

        $this->assertSame(TriageFlowVersion::STATUS_REVIEWED, $version->fresh()->status);

        $publication->publish($version->fresh(), $other);
        $this->assertSame(TriageFlowVersion::STATUS_PUBLISHED, $version->fresh()->status);
    }

    public function test_changes_requested_on_the_published_version_unpublishes_it(): void
    {
        $this->writeExample();
        app(FlowImporter::class)->import();
        $this->approve($this->version(1));
        app(FlowPublication::class)->publish($this->version(1), null);
        $this->getJson('/api/v1/triage/v2/catalog')->assertJsonCount(1, 'data.flows');

        app(FlowPublication::class)->recordReview($this->version(1), [
            'decision' => TriageFlowReview::DECISION_CHANGES_REQUESTED,
            'reviewed_on' => '2026-10-07',
            'note' => 'Грешка во препораката.',
        ], null);

        $this->assertSame(TriageFlowVersion::STATUS_RETIRED, $this->version(1)->status);
        $this->assertSame([], $this->getJson('/api/v1/triage/v2/catalog')->json('data.flows') ?? []);
        $this->assertNotNull(app(FlowPublication::class)->blocker($this->version(1)));
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

    public function test_in_a_deployed_environment_general_imports_as_a_draft_needing_review(): void
    {
        config(['triage.flows_path' => database_path('data/triage/flows')]);
        $this->app['env'] = 'production';
        $this->assertTrue(DeploymentEnvironment::isDeployed());

        app(FlowImporter::class)->import();

        $general = TriageFlow::query()->where('key', 'general')->firstOrFail();
        $this->assertSame(TriageFlowVersion::STATUS_DRAFT, $general->versions()->firstOrFail()->status);
        $this->assertNull($general->versions()->firstOrFail()->review_exempt_reason);
        $this->assertSame(0, TriageFlowVersion::query()->where('status', 'published')->count());
        $this->assertSame([], $this->getJson('/api/v1/triage/v2/catalog')->json('data.flows') ?? []);
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
