<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Filament\Pages\UxAnalysis;
use App\Models\User;
use App\Support\Ux\UxEventRecorder;
use App\Support\Ux\UxOverlayToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Admin „UX анализа“: the anonymous counters as tables and funnels, and the
 * staff-only heatmap link.
 */
class UxAnalysisPageTest extends TestCase
{
    use RefreshDatabase;

    private function staff(bool $analytics = true): User
    {
        Permission::findOrCreate('analytics.view', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create([
            'user_kind' => UserKind::Staff,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        if ($analytics) {
            $user->givePermissionTo('analytics.view');
        }

        return $user;
    }

    public function test_it_shows_dead_and_rage_click_targets_with_descriptions(): void
    {
        app(UxEventRecorder::class)->record([
            ['r' => '/', 'vc' => 'mobile', 'wb' => 320, 'x' => 50, 'y' => 30, 'k' => 'doctor-card/heading', 'd' => true, 'g' => true],
            ['r' => '/', 'vc' => 'mobile', 'wb' => 320, 'x' => 50, 'y' => 30, 'k' => 'main/link', 'd' => false, 'g' => false],
        ], [['r' => '/', 'vc' => 'mobile', 's' => 90, 't' => 2]]);

        Livewire::actingAs($this->staff())
            ->test(UxAnalysis::class)
            ->assertOk()
            ->assertSee('Картичка на лекар → наслов (не е интерактивно)')
            ->assertSee('doctor-card/heading')
            ->assertSee('Главна содржина → линк')
            ->assertSee('1 посети на /');
    }

    public function test_it_states_the_configured_retention(): void
    {
        config(['ux.retention_days' => 90]);

        Livewire::actingAs($this->staff())
            ->test(UxAnalysis::class)
            ->assertSee('Се чуваат 90 дена.')
            ->assertDontSee('Се чуваат 180 дена.');
    }

    public function test_it_is_hidden_from_staff_without_analytics(): void
    {
        $this->actingAs($this->staff(analytics: false))
            ->get('/admin/ux-analysis')
            ->assertForbidden();
    }

    public function test_the_overlay_link_carries_a_valid_token_in_the_fragment(): void
    {
        config(['zdravje.frontend_url' => 'https://zdravje.test']);
        $staff = $this->staff();

        $url = Livewire::actingAs($staff)
            ->test(UxAnalysis::class)
            ->set('route', '/doctors')
            ->set('days', 7)
            ->call('createOverlayLink')
            ->get('overlayUrl');

        $this->assertIsString($url);
        $this->assertStringStartsWith('https://zdravje.test/doctors#ux-heatmap=', $url);

        $grant = UxOverlayToken::verify(substr($url, strpos($url, '=') + 1));
        $this->assertNotNull($grant);
        $this->assertSame($staff->id, $grant['user']->id);
        $this->assertSame(7, $grant['days']);
    }

    public function test_an_unsafe_sample_path_falls_back_to_the_template(): void
    {
        config(['zdravje.frontend_url' => 'https://zdravje.test']);

        $url = Livewire::actingAs($this->staff())
            ->test(UxAnalysis::class)
            ->set('route', '/forum')
            ->set('samplePath', '//evil.example/x')
            ->call('createOverlayLink')
            ->get('overlayUrl');

        $this->assertStringStartsWith('https://zdravje.test/forum#ux-heatmap=', (string) $url);
    }
}
