<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Mail\AccountExistsMail;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\FrontendUrl;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Account takeover and account-existence regressions around sign-up, sign-in and
 * password recovery.
 */
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
        $this->forgetRateLimits();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function registration(array $overrides = []): array
    {
        $password = $overrides['password'] ?? 'sufficiently1long';

        return array_merge([
            'name' => 'New Member',
            'email' => 'victim@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ], $overrides);
    }

    private function latestVerificationLink(User $user): string
    {
        $sent = Notification::sent($user->fresh(), VerifyEmailNotification::class);
        $this->assertNotEmpty($sent, 'No verification link was sent.');

        // Built now, against the account as it stands now — which is what a
        // worker would do at the moment it sends the mail.
        return $sent->last()->toMail($user->fresh())->actionUrl;
    }

    // ---- account pre-hijacking ----

    private function registerAs(string $name, string $password): void
    {
        $this->forgetRateLimits();
        $this->postJson('/api/v1/auth/register', $this->registration([
            'name' => $name,
            'password' => $password,
        ]))->assertStatus(202);
    }

    private function assertCannotSignIn(string $password): void
    {
        $this->forgetRateLimits();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@example.com',
            'password' => $password,
        ])->assertUnprocessable();
    }

    private function adminWithAddress(string $email, ?string $verifiedAt): User
    {
        $admin = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => $email,
            'password' => 'staff1password',
            'role' => UserRole::Admin,
            'user_kind' => UserKind::Staff,
            'email_verified_at' => $verifiedAt,
        ]);
        $admin->syncRoles(['Administrator']);

        return $admin;
    }

    public function test_registering_over_an_unverified_staff_account_neither_takes_it_over_nor_mails_it_a_link(): void
    {
        Notification::fake();
        Mail::fake();

        // Created in Filament before staff were verified on creation.
        $admin = $this->adminWithAddress('ops@example.com', null);

        $fresh = $this->postJson('/api/v1/auth/register', $this->registration(['email' => 'fresh@example.com']));
        $this->forgetRateLimits();
        $hijack = $this->postJson('/api/v1/auth/register', $this->registration([
            'name' => 'Attacker',
            'email' => 'ops@example.com',
            'password' => 'attacker1password',
        ]));

        $this->assertSame(202, $hijack->status());
        $this->assertSame($fresh->json(), $hijack->json());

        $admin->refresh();
        $this->assertSame('Platform Admin', $admin->name);
        $this->assertTrue(Hash::check('staff1password', $admin->password));
        $this->assertFalse(Hash::check('attacker1password', $admin->password), 'A public sign-up replaced a staff password.');
        $this->assertNull($admin->registration_contested_at);
        Notification::assertNotSentTo($admin, VerifyEmailNotification::class);
        Mail::assertQueued(AccountExistsMail::class, fn (AccountExistsMail $mail): bool => $mail->hasTo('ops@example.com'));
    }

    public function test_re_registering_never_replaces_the_pending_credentials(): void
    {
        Notification::fake();

        $this->registerAs('First', 'first1password');
        $this->registerAs('Second', 'second1password');

        $user = User::query()->where('email', 'victim@example.com')->sole();

        $this->assertSame('First', $user->name);
        $this->assertTrue(Hash::check('first1password', $user->password));
        $this->assertNotNull($user->registration_contested_at);
    }

    public function test_attacker_first_then_owner_the_owner_ends_up_choosing_the_password(): void
    {
        Notification::fake();

        $this->registerAs('Attacker', 'attacker1password');
        $this->registerAs('Real Owner', 'owner1password');

        $user = User::query()->where('email', 'victim@example.com')->sole();

        $this->get($this->latestVerificationLink($user))
            ->assertRedirectContains('/verify-email?status=verified_set_password');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->registration_contested_at);
        $this->assertCannotSignIn('attacker1password');
        $this->assertCannotSignIn('owner1password');
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_owner_first_then_attacker_the_owners_link_still_works_and_leads_to_a_reset(): void
    {
        Notification::fake();

        $this->registerAs('Real Owner', 'owner1password');
        $user = User::query()->where('email', 'victim@example.com')->sole();
        $ownersLink = $this->latestVerificationLink($user);

        // Re-registering cannot invalidate a link the owner already holds.
        $this->registerAs('Attacker', 'attacker1password');

        $this->get($ownersLink)->assertRedirectContains('/verify-email?status=verified_set_password');

        $this->assertCannotSignIn('attacker1password');
        $this->assertCannotSignIn('owner1password');

        // The reset link reaches the mailbox, and completes the account.
        $reset = Notification::sent($user, ResetPasswordNotification::class)->sole();
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'victim@example.com',
            'token' => $reset->token,
            'password' => 'chosen1bythemailbox',
            'password_confirmation' => 'chosen1bythemailbox',
        ])->assertOk();

        $this->forgetRateLimits();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@example.com',
            'password' => 'chosen1bythemailbox',
        ])->assertOk();
    }

    public function test_verifying_a_contested_account_revokes_its_tokens(): void
    {
        Notification::fake();

        $this->registerAs('Attacker', 'attacker1password');
        $this->registerAs('Real Owner', 'owner1password');

        $user = User::query()->where('email', 'victim@example.com')->sole();
        $user->createToken('minted-somehow');

        $this->get($this->latestVerificationLink($user))
            ->assertRedirectContains('status=verified_set_password');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_an_uncontested_registration_activates_its_password_with_an_unbound_link(): void
    {
        Notification::fake();

        $this->registerAs('Real Owner', 'owner1password');
        $user = User::query()->where('email', 'victim@example.com')->sole();
        $link = $this->latestVerificationLink($user);

        // Links no longer carry anything derived from the password.
        $this->assertStringNotContainsString('credential=', $link);

        $this->get($link)->assertRedirect(FrontendUrl::to('/verify-email?status=verified'));

        $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@example.com',
            'password' => 'owner1password',
        ])->assertOk();
        Notification::assertNotSentTo($user, ResetPasswordNotification::class);
    }

    /**
     * Queued mail builds its link in the worker, where there is no incoming
     * request: the URL comes from APP_URL alone. It still has to validate on the
     * API's signed route.
     */
    public function test_a_link_built_without_a_request_validates_on_the_api(): void
    {
        Notification::fake();
        config(['app.url' => 'https://api.example.test']);

        $user = User::factory()->create(['email' => 'victim@example.com', 'email_verified_at' => null]);

        // What a queue worker has: the console request, seeded from APP_URL.
        (new SetRequestForConsole)->bootstrap($this->app);
        $link = (new VerifyEmailNotification)->toMail($user)->actionUrl;

        $this->assertStringStartsWith('https://api.example.test/api/v1/auth/email/verify/', $link);

        $this->get($link)->assertRedirect(FrontendUrl::to('/verify-email?status=verified'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_re_registering_an_unverified_address_answers_like_a_new_one(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'pending@example.com', 'email_verified_at' => null]);

        $fresh = $this->postJson('/api/v1/auth/register', $this->registration(['email' => 'fresh@example.com']));
        $this->forgetRateLimits();
        $pending = $this->postJson('/api/v1/auth/register', $this->registration(['email' => 'pending@example.com']));

        $this->assertSame(202, $fresh->status());
        $this->assertSame(202, $pending->status());
        $this->assertSame($fresh->json(), $pending->json());
    }

    // ---- password reset revokes existing sessions ----

    public function test_resetting_a_password_revokes_every_api_token(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $token = $user->createToken('stolen')->plainTextToken;
        $user->createToken('other-device');

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'owner@example.com',
            'token' => Password::createToken($user),
            'password' => 'brand1newpassword',
            'password_confirmation' => 'brand1newpassword',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    // ---- timing: nothing slow happens inline only for real accounts ----

    public function test_verification_and_reset_mail_are_queued_not_sent_inline(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'known@example.com']);

        $this->postJson('/api/v1/auth/register', $this->registration())->assertStatus(202);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.com'])->assertOk();

        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job): bool => $job->notification instanceof VerifyEmailNotification,
        );
        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job): bool => $job->notification instanceof ResetPasswordNotification
                && $job->notifiables->contains($user),
        );
    }

    public function test_login_pays_the_hash_cost_for_an_unknown_address(): void
    {
        Hash::spy();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever1password',
        ])->assertUnprocessable();

        Hash::shouldHaveReceived('check')->once();
    }

    public function test_forgot_password_pays_the_hash_cost_for_an_unknown_address(): void
    {
        Notification::fake();
        Hash::spy();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        Hash::shouldHaveReceived('make')->once();
    }

    // ---- email addresses are case-insensitive identities ----

    public function test_registration_stores_a_normalised_address(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->registration(['email' => '  Victim@Example.COM ']))
            ->assertStatus(202);
        $this->forgetRateLimits();
        $this->postJson('/api/v1/auth/register', $this->registration(['email' => 'victim@example.com']))
            ->assertStatus(202);

        $this->assertSame(['victim@example.com'], User::query()->pluck('email')->all());
    }

    public function test_every_auth_endpoint_matches_the_address_case_insensitively(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'sufficiently1long',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'Owner@Example.com',
            'password' => 'sufficiently1long',
        ])->assertOk();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'OWNER@example.com'])->assertOk();
        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'Owner@example.com',
            'token' => Password::createToken($user),
            'password' => 'brand1newpassword',
            'password_confirmation' => 'brand1newpassword',
        ])->assertOk();

        $user->forceFill(['email_verified_at' => null])->save();
        $this->forgetRateLimits();
        $this->postJson('/api/v1/auth/email/resend', ['email' => 'OWNER@EXAMPLE.COM'])->assertStatus(202);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_the_model_normalises_addresses_set_anywhere(): void
    {
        $user = User::factory()->create(['email' => ' Staff@Example.com']);

        $this->assertSame('staff@example.com', $user->fresh()->email);
    }

    public function test_the_migration_lowercases_existing_addresses(): void
    {
        $id = $this->insertRawUser('Mixed@Example.com');

        $this->runEmailMigration();

        $this->assertSame('mixed@example.com', DB::table('users')->where('id', $id)->value('email'));
    }

    public function test_the_migration_refuses_to_merge_colliding_addresses(): void
    {
        $this->insertRawUser('Dup@Example.com');
        $this->insertRawUser('dup@example.com');

        try {
            $this->runEmailMigration();
            $this->fail('The migration silently accepted two accounts that normalise to one address.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('dup@example.com', $e->getMessage());
        }

        // Nothing was half-applied.
        $this->assertTrue(DB::table('users')->where('email', 'Dup@Example.com')->exists());
    }

    // ---- per-address mail ceiling cannot strand the owner ----

    public function test_a_stranger_exhausting_the_address_ceiling_does_not_block_the_owner(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'pending@example.com',
            'password' => 'sufficiently1long',
            'email_verified_at' => null,
        ]);

        // A stranger burns the per-address budget from the public resend form.
        for ($i = 0; $i < 8; $i++) {
            $this->travel(61)->seconds();
            $this->postJson('/api/v1/auth/email/resend', ['email' => 'pending@example.com'])->assertStatus(202);
        }
        $publicSends = Notification::sent($user, VerifyEmailNotification::class)->count();

        // The owner, who knows the password, still gets a link.
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'pending@example.com',
            'password' => 'sufficiently1long',
        ])->assertForbidden()->assertJsonPath('code', 'auth.email_unverified');

        $this->assertSame(
            $publicSends + 1,
            Notification::sent($user, VerifyEmailNotification::class)->count(),
        );
    }

    private function insertRawUser(string $email): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Legacy',
            'email' => $email,
            'password' => Hash::make('sufficiently1long'),
            'role' => 'member',
            'user_kind' => 'client',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function runEmailMigration(): void
    {
        $files = glob(database_path('migrations/*_normalise_user_emails.php')) ?: [];
        $this->assertCount(1, $files);

        (require $files[0])->up();
    }
}
