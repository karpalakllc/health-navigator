<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserKind;
use App\Models\User;
use App\Support\Ux\UxEventRecorder;
use App\Support\Ux\UxOverlayToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * GET /ux/heatmap: the staff overlay's data. It must never answer without a
 * valid, unexpired overlay token of someone who may see analytics.
 */
class UxHeatmapTest extends TestCase
{
    use RefreshDatabase;

    private function analyst(): User
    {
        Permission::findOrCreate('analytics.view', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['user_kind' => UserKind::Staff]);
        $user->givePermissionTo('analytics.view');

        return $user;
    }

    private function seedClicks(): void
    {
        app(UxEventRecorder::class)->record([
            ['r' => '/doctors', 'vc' => 'desktop', 'wb' => 1440, 'x' => 50, 'y' => 30, 'k' => 'doctor-card/heading', 'd' => true, 'g' => false],
            ['r' => '/doctors', 'vc' => 'desktop', 'wb' => 1440, 'x' => 50, 'y' => 30, 'k' => 'doctor-card/heading', 'd' => true, 'g' => true],
            ['r' => '/doctors', 'vc' => 'mobile', 'wb' => 320, 'x' => 10, 'y' => 5, 'k' => 'main/link', 'd' => false, 'g' => false],
        ], [
            ['r' => '/doctors', 'vc' => 'desktop', 's' => 50, 't' => 0],
        ]);
    }

    private function heatmap(?string $token, string $query = 'route=/doctors&vc=desktop')
    {
        return $this->withHeaders($token === null ? [] : ['X-Ux-Overlay-Token' => $token])
            ->getJson('/api/v1/ux/heatmap?'.$query);
    }

    public function test_a_valid_token_reads_the_cells_for_one_device_class(): void
    {
        $this->seedClicks();
        $token = UxOverlayToken::issue($this->analyst(), 30);

        $response = $this->heatmap($token)->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $response->assertJsonPath('data.route', '/doctors')
            ->assertJsonPath('data.viewport_class', 'desktop')
            ->assertJsonPath('data.cells', [['x' => 50, 'y' => 30, 'clicks' => 2, 'dead' => 2, 'rage' => 1]])
            ->assertJsonPath('data.page.views', 1)
            ->assertJsonPath('data.page.scroll.50', 1)
            ->assertJsonPath('data.page.scroll.75', 0);
    }

    public function test_the_width_filter_keeps_neighbouring_widths_only(): void
    {
        $this->seedClicks();
        $token = UxOverlayToken::issue($this->analyst(), 30);

        $this->heatmap($token, 'route=/doctors&vc=desktop&wb=1360')->assertJsonCount(1, 'data.cells');
        $this->heatmap($token, 'route=/doctors&vc=desktop&wb=1920')->assertJsonCount(0, 'data.cells');
    }

    public function test_without_a_token_nothing_is_read(): void
    {
        $this->seedClicks();

        $this->heatmap(null)->assertForbidden()->assertJsonMissingPath('data');
        $this->heatmap('')->assertForbidden();
        $this->heatmap('not-a-token')->assertForbidden();
    }

    public function test_a_forged_or_tampered_token_is_refused(): void
    {
        $analyst = $this->analyst();
        $token = UxOverlayToken::issue($analyst, 30);
        [$body, $signature] = explode('.', $token);

        // Same payload shape, longer window, signature kept: must not verify.
        $tampered = rtrim(strtr(base64_encode((string) json_encode([
            'u' => $analyst->id, 'e' => now()->addYear()->getTimestamp(), 'd' => 180,
        ])), '+/', '-_'), '=').'.'.$signature;

        $this->heatmap($tampered)->assertForbidden();
        $this->heatmap($body.'.'.strrev($signature))->assertForbidden();
    }

    public function test_an_expired_token_is_refused(): void
    {
        $token = UxOverlayToken::issue($this->analyst(), 30);

        Carbon::setTestNow(now()->addMinutes(121));

        $this->heatmap($token)->assertForbidden();

        Carbon::setTestNow();
    }

    public function test_a_token_stops_working_when_the_permission_is_withdrawn(): void
    {
        $analyst = $this->analyst();
        $token = UxOverlayToken::issue($analyst, 30);

        $analyst->revokePermissionTo('analytics.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->heatmap($token)->assertForbidden();
    }

    public function test_a_token_for_a_member_without_the_permission_is_refused(): void
    {
        $member = User::factory()->create();

        $this->heatmap(UxOverlayToken::issue($member, 30))->assertForbidden();
    }

    public function test_the_page_must_be_a_known_template(): void
    {
        $token = UxOverlayToken::issue($this->analyst(), 30);

        $this->heatmap($token, 'route=/account&vc=desktop')->assertUnprocessable();
        $this->heatmap($token, 'route=/doctors&vc=watch')->assertUnprocessable();
    }
}
