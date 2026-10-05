<?php

namespace Tests\Feature\Console;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PlatformUserSeeder;
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

    public function test_it_accepts_the_default_admin_email_in_development(): void
    {
        // The seeder creates this very address in development; bootstrap
        // refusing it there was the two disagreeing about the environment.
        config(['zdravje.admin.email' => 'admin@zdravje360.test']);
        $this->app['env'] = 'development';

        $this->artisan('platform:bootstrap')->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'admin@zdravje360.test')->sole()->hasRole('Administrator'));
    }

    public function test_a_mixed_case_admin_email_finds_the_admin_on_later_runs(): void
    {
        config(['zdravje.admin.email' => 'Ops@Example.com']);

        $this->artisan('platform:bootstrap')->assertSuccessful();
        $this->artisan('platform:bootstrap')->assertSuccessful();

        $this->assertSame(['ops@example.com'], User::query()->pluck('email')->all());
    }

    public function test_a_mixed_case_admin_email_still_refuses_a_lowercase_squatter(): void
    {
        config(['zdravje.admin.email' => 'Ops@Example.com']);
        $squatter = User::factory()->create([
            'email' => 'ops@example.com',
            'role' => UserRole::Member,
            'user_kind' => UserKind::Client,
        ]);

        $this->artisan('platform:bootstrap')->assertFailed();

        $this->assertFalse($squatter->fresh()->hasRole('Administrator'));
        $this->assertSame(1, User::query()->count());
    }

    public function test_promoting_an_existing_account_verifies_it(): void
    {
        $owner = User::factory()->unverified()->create([
            'email' => self::EMAIL,
            'role' => UserRole::Member,
            'user_kind' => UserKind::Client,
        ]);

        $this->artisan('platform:bootstrap', ['--promote-existing' => true])->assertSuccessful();

        // Unverified staff are what a public sign-up could treat as pending.
        $this->assertNotNull($owner->fresh()->email_verified_at);
    }

    public function test_the_demo_seeder_matches_mixed_case_configured_addresses(): void
    {
        config([
            'zdravje.admin.email' => 'Ops@Example.com',
            'zdravje.seed.moderator.email' => 'Mod@Example.com',
            'zdravje.seed.member.email' => 'Member@Example.com',
        ]);

        $this->seed(PlatformUserSeeder::class);
        $this->seed(PlatformUserSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['ops@example.com', 'mod@example.com', 'member@example.com'],
            User::query()->pluck('email')->all(),
        );
    }

    public function test_the_migration_verifies_existing_staff_only(): void
    {
        $staff = User::factory()->unverified()->create(['user_kind' => UserKind::Staff, 'role' => UserRole::Moderator]);
        $client = User::factory()->unverified()->create(['user_kind' => UserKind::Client]);

        $files = glob(database_path('migrations/*_verify_existing_staff_accounts.php')) ?: [];
        $this->assertCount(1, $files);
        (require $files[0])->up();

        $this->assertNotNull($staff->fresh()->email_verified_at);
        $this->assertNull($client->fresh()->email_verified_at);
    }
}
