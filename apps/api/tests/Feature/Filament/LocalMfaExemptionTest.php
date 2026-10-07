<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalMfaExemptionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole(RoleCatalog::ensure(RoleCatalog::ADMINISTRATOR));

        return $user;
    }

    public function test_a_listed_admin_skips_two_factor_only_when_the_app_is_local(): void
    {
        config(['zdravje.mfa.local_exempt_emails' => ['owner@zdravje360.test']]);
        $owner = $this->admin('Owner@Zdravje360.test');
        $other = $this->admin('other@zdravje360.test');

        $this->app['env'] = 'local';
        $this->assertFalse($owner->requiresMultiFactorAuthentication());
        $this->assertTrue($other->requiresMultiFactorAuthentication());

        foreach (['development', 'staging', 'production', 'testing'] as $environment) {
            $this->app['env'] = $environment;
            $this->assertTrue($owner->requiresMultiFactorAuthentication(), $environment);
        }
    }

    public function test_the_exempt_admin_reaches_the_panel_without_enrolling_locally(): void
    {
        config(['zdravje.mfa.local_exempt_emails' => ['owner@zdravje360.test']]);
        $owner = $this->admin('owner@zdravje360.test');
        $this->app['env'] = 'local';

        $this->actingAs($owner)->get('/admin')->assertOk();
    }

    public function test_without_the_setting_admins_are_sent_to_set_up(): void
    {
        $owner = $this->admin('owner@zdravje360.test');
        $this->app['env'] = 'local';

        $this->actingAs($owner)->get('/admin')->assertRedirectContains('multi-factor-authentication');
    }
}
