<?php

namespace Tests\Feature\Api\V1\Usernames;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\RoleCatalog;
use App\Support\Usernames\TemporaryUsername;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * A sign-up's username is held only once the address is verified, so the
 * availability check cannot tell whether an address already has an account,
 * and never-verified sign-ups are pruned.
 */
class RequestedUsernameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
        $this->forgetRateLimits();
        Notification::fake();
        Mail::fake();
    }

    private function register(string $email, string $username): void
    {
        $this->forgetRateLimits();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Марија Костовска',
            'username' => $username,
            'email' => $email,
            'password' => 'sufficiently1long',
            'password_confirmation' => 'sufficiently1long',
            'accept_terms' => true,
        ])->assertStatus(202);
    }

    private function availability(string $username): string
    {
        $this->forgetRateLimits();

        return (string) $this->getJson('/api/v1/usernames/availability?username='.$username)
            ->assertOk()
            ->getContent();
    }

    private function verify(User $user): void
    {
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]))->assertRedirect();
    }

    public function test_existing_and_new_address_sign_ups_leave_availability_indistinguishable(): void
    {
        User::factory()->create(['email' => 'victim@example.com', 'username' => 'Victim']);

        $this->register('victim@example.com', 'probeone');
        $this->register('nobody@example.com', 'probetwo');

        $this->assertSame($this->availability('probeone'), $this->availability('probetwo'));
        $this->assertSame(
            ['data' => ['available' => true, 'message' => null]],
            json_decode($this->availability('probetwo'), true),
        );

        // Nor does signing up with the same name tell the two apart.
        $this->register('third@example.com', 'probetwo');
    }

    public function test_an_unverified_account_carries_a_temporary_name(): void
    {
        $this->register('marija@example.com', 'Bitolchanka');

        $user = User::query()->where('email', 'marija@example.com')->sole();

        $this->assertTrue(TemporaryUsername::isTemporary($user->username));
        $this->assertTrue($user->must_choose_username);
        $this->assertSame('Bitolchanka', $user->requested_username);
    }

    public function test_verification_gives_the_requested_name(): void
    {
        $this->register('marija@example.com', 'Bitolchanka');
        $user = User::query()->where('email', 'marija@example.com')->sole();

        $this->verify($user);

        $user->refresh();
        $this->assertSame('Bitolchanka', $user->username);
        $this->assertFalse($user->must_choose_username);
        $this->assertNull($user->requested_username);
        $this->assertNull($user->username_changed_at);
    }

    public function test_a_name_taken_before_verification_leaves_the_member_to_choose(): void
    {
        $this->register('first@example.com', 'Bitolchanka');
        $this->register('second@example.com', 'битолчанка');

        $first = User::query()->where('email', 'first@example.com')->sole();
        $second = User::query()->where('email', 'second@example.com')->sole();

        $this->verify($second);
        $this->verify($first);

        $this->assertSame('битолчанка', $second->refresh()->username);

        $first->refresh();
        $this->assertTrue(TemporaryUsername::isTemporary($first->username));
        $this->assertTrue($first->must_choose_username);
        $this->assertNull($first->requested_username);
        $this->assertNotNull($first->email_verified_at);
    }

    public function test_a_password_reset_that_verifies_the_address_gives_the_name_too(): void
    {
        $this->register('marija@example.com', 'Bitolchanka');
        $user = User::query()->where('email', 'marija@example.com')->sole();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'marija@example.com',
            'token' => Password::createToken($user),
            'password' => 'another1longpassword',
            'password_confirmation' => 'another1longpassword',
        ])->assertOk();

        $this->assertSame('Bitolchanka', $user->refresh()->username);
        $this->assertFalse($user->must_choose_username);
    }

    public function test_never_verified_sign_ups_are_pruned(): void
    {
        $this->travelTo(now()->subDays(8));
        $this->register('old@example.com', 'oldsignup');
        $this->register('contested@example.com', 'contested');
        $staff = User::factory()->unverified()->create(['email' => 'granted@example.com']);
        $staff->assignRole(RoleCatalog::ensure(RoleCatalog::MEMBER));
        $staff->assignRole(RoleCatalog::ensure(RoleCatalog::MODERATOR));
        $verified = User::factory()->create(['email' => 'verified@example.com']);
        $this->travelBack();

        // Contested two days ago: the owner may hold a fresh link.
        User::query()->where('email', 'contested@example.com')->update(['registration_contested_at' => now()->subDays(2)]);
        $this->register('young@example.com', 'youngsignup');

        $this->artisan('accounts:prune-unverified')->assertSuccessful();

        $this->assertSame(
            ['contested@example.com', 'granted@example.com', 'verified@example.com', 'young@example.com'],
            User::query()->orderBy('email')->pluck('email')->all(),
        );
        $this->assertTrue($verified->fresh() !== null);
    }

    public function test_the_prune_window_is_configurable(): void
    {
        $this->travelTo(now()->subDays(3));
        $this->register('old@example.com', 'oldsignup');
        $this->travelBack();

        $this->artisan('accounts:prune-unverified')->assertSuccessful();
        $this->assertSame(1, User::query()->count());

        config(['zdravje.accounts.unverified_prune_days' => 2]);
        $this->artisan('accounts:prune-unverified')->assertSuccessful();
        $this->assertSame(0, User::query()->count());
    }
}
