<?php

namespace Tests\Feature\Console;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'ops@example.test';

    private const STRONG_PASSWORD = 'correct1horse1battery';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'zdravje.admin.email' => self::EMAIL,
            'zdravje.admin.password' => self::STRONG_PASSWORD,
        ]);
    }

    public function test_it_creates_the_admin_on_a_fresh_database(): void
    {
        $this->artisan('platform:bootstrap')->assertSuccessful();

        $admin = User::query()->where('email', self::EMAIL)->firstOrFail();
        $this->assertTrue($admin->hasRole('Administrator'));
        $this->assertSame(UserKind::Staff, $admin->user_kind);
        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $admin->password));
    }

    public function test_it_refuses_a_weak_admin_password(): void
    {
        config(['zdravje.admin.password' => 'password']);

        $this->artisan('platform:bootstrap')->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
    }

    public function test_it_refuses_to_promote_a_self_registered_account(): void
    {
        $squatter = User::factory()->unverified()->create([
            'email' => self::EMAIL,
            'role' => UserRole::Member,
            'user_kind' => UserKind::Client,
        ]);

        $this->artisan('platform:bootstrap')->assertFailed();

        $squatter->refresh();
        $this->assertFalse($squatter->hasRole('Administrator'));
        $this->assertSame(UserKind::Client, $squatter->user_kind);
    }

    public function test_it_promotes_an_existing_account_only_when_asked(): void
    {
        $owner = User::factory()->create([
            'email' => self::EMAIL,
            'role' => UserRole::Member,
            'user_kind' => UserKind::Client,
        ]);

        $this->artisan('platform:bootstrap', ['--promote-existing' => true])->assertSuccessful();

        $owner->refresh();
        $this->assertTrue($owner->hasRole('Administrator'));
        $this->assertSame(UserKind::Staff, $owner->user_kind);
    }

    public function test_it_is_idempotent_for_an_existing_staff_admin(): void
    {
        $this->artisan('platform:bootstrap')->assertSuccessful();
        $this->artisan('platform:bootstrap')->assertSuccessful();

        $this->assertSame(1, User::query()->where('email', self::EMAIL)->count());
    }

    public function test_it_refuses_the_default_admin_email_outside_local_and_testing(): void
    {
        config(['zdravje.admin.email' => 'admin@zdravje360.test']);
        $this->app['env'] = 'production';

        $this->artisan('platform:bootstrap')->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin@zdravje360.test']);
    }
}
