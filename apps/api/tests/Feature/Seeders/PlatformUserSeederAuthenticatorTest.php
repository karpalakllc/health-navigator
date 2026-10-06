<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\PlatformUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformUserSeederAuthenticatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_staff_arrive_with_an_authenticator(): void
    {
        $this->seed(PlatformUserSeeder::class);

        $admin = User::query()->where('email', config('zdravje.admin.email'))->firstOrFail();
        $moderator = User::query()->where('email', config('zdravje.seed.moderator.email'))->firstOrFail();
        $member = User::query()->where('email', config('zdravje.seed.member.email'))->firstOrFail();

        $this->assertSame(PlatformUserSeeder::DEMO_ADMIN_TOTP_SECRET, $admin->app_authentication_secret);
        $this->assertSame(PlatformUserSeeder::DEMO_MODERATOR_TOTP_SECRET, $moderator->app_authentication_secret);
        $this->assertNull($member->app_authentication_secret);
    }

    public function test_a_reseed_keeps_an_authenticator_the_owner_set_up(): void
    {
        $this->seed(PlatformUserSeeder::class);

        $admin = User::query()->where('email', config('zdravje.admin.email'))->firstOrFail();
        $admin->forceFill(['app_authentication_secret' => 'OWNERCHOSENSECRET'])->save();

        $this->seed(PlatformUserSeeder::class);

        $this->assertSame('OWNERCHOSENSECRET', $admin->fresh()->app_authentication_secret);
    }
}
