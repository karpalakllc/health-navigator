<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Filament\Pages\GuidanceOutcomes;
use App\Filament\Pages\GuidanceSimulator;
use App\Filament\Resources\GuidanceFlows\Pages\ListGuidanceFlows;
use App\Filament\Resources\GuidanceFlows\Pages\ViewGuidanceFlow;
use App\Filament\Resources\GuidanceFlows\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\TriageFlows\Pages\ListTriageFlows;
use App\Models\TriageFlow;
use App\Models\TriageFlowVersion;
use App\Models\TriageOutcomeStat;
use App\Models\TriageSession;
use App\Models\User;
use App\Services\Triage\V2\FlowImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Admin „Guidance flows“: import from files, version history, the simulator,
 * clinician sign-off and publication, and the anonymous outcome counts.
 */
class GuidanceFlowAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['triage.flows_path' => base_path('tests/Fixtures/triage')]);
    }

    /** @param  list<string>  $abilities */
    private function staff(array $abilities = ['view', 'create', 'update']): User
    {
        foreach (['view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("triage_flows.{$ability}", 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create([
            'user_kind' => UserKind::Staff,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
        $user->givePermissionTo(array_map(fn ($a) => "triage_flows.{$a}", $abilities));

        return $user;
    }

    private function exampleVersion(): TriageFlowVersion
    {
        app(FlowImporter::class)->import();

        return TriageFlow::query()->where('key', 'example-sore-throat')->firstOrFail()->versions()->firstOrFail();
    }

    public function test_import_from_files_lists_flows_as_drafts_awaiting_review(): void
    {
        Livewire::actingAs($this->staff())
            ->test(ListGuidanceFlows::class)
            ->callAction('import')
            ->assertNotified();

        Livewire::actingAs($this->staff())
            ->test(ListGuidanceFlows::class)
            ->assertSee('Болки во грлото (пример)')
            ->assertSee('нацрт — чека лекарски преглед');

        $this->assertSame(2, TriageFlowVersion::query()->where('status', 'draft')->count());
    }

    public function test_view_only_staff_cannot_import_review_or_publish(): void
    {
        $version = $this->exampleVersion();
        $viewer = $this->staff(['view']);

        Livewire::actingAs($viewer)->test(ListGuidanceFlows::class)->assertActionHidden('import');

        Livewire::actingAs($viewer)
            ->test(VersionsRelationManager::class, ['ownerRecord' => $version->flow, 'pageClass' => ViewGuidanceFlow::class])
            ->assertTableActionHidden('review', $version)
            ->assertTableActionHidden('publish', $version);
    }

    public function test_clinician_review_then_publish_from_the_version_history(): void
    {
        $version = $this->exampleVersion();
        $staff = $this->staff();

        $component = Livewire::actingAs($staff)
            ->test(VersionsRelationManager::class, ['ownerRecord' => $version->flow, 'pageClass' => ViewGuidanceFlow::class]);

        // Publishing before a review changes nothing.
        $component->callTableAction('publish', $version);
        $this->assertSame('draft', $version->fresh()->status);

        $component->callTableAction('review', $version, [
            'decision' => 'approved',
            'reviewer_name' => 'д-р Тест',
            'reviewer_registration' => '',
            'reviewed_on' => now()->toDateString(),
            'note' => 'Прегледано во симулаторот.',
        ])->assertHasNoTableActionErrors();

        $this->assertSame('reviewed', $version->fresh()->status);
        $this->assertSame($staff->id, $version->fresh()->latestReview->recorded_by);

        $component->callTableAction('publish', $version);
        $this->assertSame('published', $version->fresh()->status);
        $this->assertSame($staff->id, $version->fresh()->published_by);

        $this->getJson('/api/v1/triage/v2/catalog')->assertOk()->assertJsonPath('data.flows.0.key', 'example-sore-throat');
    }

    public function test_the_simulator_steps_through_any_version_and_shows_the_path_and_outcome(): void
    {
        $version = $this->exampleVersion();

        Livewire::actingAs($this->staff(['view']))
            ->withQueryParams(['version' => $version->id])
            ->test(GuidanceSimulator::class)
            ->assertSee('Не можете да голтате плунка')
            ->assertSee('Колку дена ве боли грлото?')
            ->set('number', '3')
            ->call('submitNumber')
            ->call('choose', 'no')
            ->assertSee('Колку е силна болката')
            ->call('choose', '9')
            ->assertSee('Outcome: Побарајте преглед денес')
            ->call('backTo', 'q_fever')
            ->assertSee('Дали имате температура')
            ->set('redFlags', ['flow.cannot_swallow_saliva'])
            ->assertSee('Outcome: Веднаш јавете се на 194');

        // Nothing stored by simulating.
        $this->assertSame(0, TriageSession::query()->count());
        $this->assertSame(0, TriageOutcomeStat::query()->count());
    }

    public function test_outcome_counts_page_shows_the_aggregates(): void
    {
        TriageOutcomeStat::record('example-sore-throat', 'o_pharmacy', 'pharmacy_advice');
        TriageOutcomeStat::record('example-sore-throat', 'o_pharmacy', 'pharmacy_advice');

        Livewire::actingAs($this->staff(['view']))
            ->test(GuidanceOutcomes::class)
            ->assertOk()
            ->assertSee('example-sore-throat')
            ->assertSee('o_pharmacy');

        $this->assertSame(2, TriageOutcomeStat::query()->sole()->count);
    }

    public function test_the_v1_resource_no_longer_lists_v2_flows(): void
    {
        $this->exampleVersion();

        Livewire::actingAs($this->staff())
            ->test(ListTriageFlows::class)
            ->assertDontSee('Болки во грлото (пример)');
    }
}
