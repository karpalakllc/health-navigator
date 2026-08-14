<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Mail\AccountExistsMail;
use App\Mail\WelcomeMail;
use App\Models\Doctor;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Registration is verify-then-activate specifically so that it cannot be used to
 * discover which addresses have accounts. On a health platform, "does this person
 * have an account here" is itself sensitive.
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
        RateLimiter::clear('api-login');
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Member',
            'email' => 'new@example.com',
            'password' => 'sufficiently1long',
            'password_confirmation' => 'sufficiently1long',
        ], $overrides);
    }

    // ---- the core property ----

    public function test_registering_a_new_and_an_existing_address_are_indistinguishable(): void
    {
        Notification::fake();
        Mail::fake();

        User::factory()->create(['email' => 'taken@example.com', 'email_verified_at' => now()]);

        $fresh = $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'fresh@example.com']));
        RateLimiter::clear('api-login');
        $taken = $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'taken@example.com']));

        $this->assertSame(202, $fresh->status());
        $this->assertSame(202, $taken->status());
        $this->assertSame(
            $fresh->json(),
            $taken->json(),
            'Registration responses differ between a free and a taken address, which '
            .'makes the endpoint an account-existence oracle.',
        );
    }

    public function test_registration_never_returns_a_session(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->payload());

        $response->assertStatus(202);
        $this->assertNull($response->json('data.token'), 'Signup must not issue a token before verification.');
        $this->assertNull($response->json('data.user'));
    }

    // ---- what actually happens behind the identical response ----

    public function test_a_new_address_creates_an_unverified_account_and_sends_a_link(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->payload())->assertStatus(202);

        $user = User::query()->where('email', 'new@example.com')->sole();

        $this->assertNull($user->email_verified_at);
        $this->assertSame(UserRole::Member, $user->role);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_an_existing_verified_address_is_told_it_already_has_an_account(): void
    {
        Mail::fake();
        Notification::fake();

        $existing = User::factory()->create([
            'email' => 'taken@example.com',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'taken@example.com']))
            ->assertStatus(202);

        $this->assertSame(1, User::query()->where('email', 'taken@example.com')->count());
        Mail::assertQueued(AccountExistsMail::class);
        Notification::assertNotSentTo($existing, VerifyEmailNotification::class);
    }

    public function test_re_registering_an_unverified_address_resends_the_verification_link(): void
    {
        Mail::fake();
        Notification::fake();

        $pending = User::factory()->create([
            'email' => 'pending@example.com',
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'pending@example.com']))
            ->assertStatus(202);

        // A "you already have an account" mail would be useless here — they cannot
        // log in yet.
        Notification::assertSentTo($pending, VerifyEmailNotification::class);
        Mail::assertNotQueued(AccountExistsMail::class);
    }

    public function test_registering_does_not_overwrite_an_existing_account(): void
    {
        Mail::fake();
        Notification::fake();

        $existing = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'taken@example.com',
            'email_verified_at' => now(),
        ]);
        $originalPassword = $existing->password;

        $this->postJson('/api/v1/auth/register', $this->payload([
            'email' => 'taken@example.com',
            'name' => 'Attacker Name',
        ]))->assertStatus(202);

        $existing->refresh();
        $this->assertSame('Original Name', $existing->name);
        $this->assertSame($originalPassword, $existing->password);
    }

    // ---- verification ----

    public function test_a_signed_link_verifies_the_address_and_sends_the_welcome_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email_verified_at' => null]);

        $this->get($this->verificationUrl($user))
            ->assertRedirectContains('/verify-email?status=verified');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Mail::assertQueued(WelcomeMail::class);
    }

    public function test_an_unsigned_or_tampered_link_is_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        // No signature at all — sent to the resend screen, not a raw 403 page.
        $this->get("/api/v1/auth/email/verify/{$user->id}/".sha1($user->email))
            ->assertRedirectContains('/verify-email?status=invalid');

        // Valid signature, wrong hash — someone else's address.
        $tampered = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.com'),
        ]);

        $this->get($tampered)->assertRedirectContains('/verify-email?status=invalid');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    /**
     * Expiry is the *ordinary* failure here — the link is opened from a mail client,
     * often well after the 60 minutes the email itself advertises. It must land on
     * the screen that offers a fresh link, not on the framework's raw 403 page.
     */
    public function test_an_expired_link_sends_the_user_somewhere_useful(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($expired)->assertRedirectContains('/verify-email?status=invalid');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verifying_twice_is_harmless(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email_verified_at' => null]);
        $url = $this->verificationUrl($user);

        $this->get($url)->assertRedirectContains('status=verified');
        $this->get($url)->assertRedirectContains('status=already');

        Mail::assertQueuedCount(1);
    }

    // ---- resend ----

    public function test_resend_is_also_non_committal(): void
    {
        Notification::fake();

        $unverified = User::factory()->create(['email' => 'pending@example.com', 'email_verified_at' => null]);

        $known = $this->postJson('/api/v1/auth/email/resend', ['email' => 'pending@example.com']);
        RateLimiter::clear('api-login');
        $unknown = $this->postJson('/api/v1/auth/email/resend', ['email' => 'ghost@example.com']);

        $this->assertSame($known->json(), $unknown->json());
        $known->assertStatus(202);
        $unknown->assertStatus(202);
        Notification::assertSentTo($unverified, VerifyEmailNotification::class);
    }

    public function test_resend_does_nothing_for_an_already_verified_address(): void
    {
        Notification::fake();

        $verified = User::factory()->create(['email' => 'done@example.com', 'email_verified_at' => now()]);

        $this->postJson('/api/v1/auth/email/resend', ['email' => 'done@example.com'])
            ->assertStatus(202);

        Notification::assertNotSentTo($verified, VerifyEmailNotification::class);
    }

    // ---- what an unverified account may do ----

    public function test_login_is_refused_until_the_address_is_verified(): void
    {
        User::factory()->create([
            'email' => 'pending@example.com',
            'password' => 'sufficiently1long',
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'pending@example.com',
            'password' => 'sufficiently1long',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.email_unverified');
    }

    /**
     * Defence in depth. Login already refuses unverified accounts, so in practice
     * they cannot obtain a token — but a token issued before an address was
     * un-verified, or any future path that mints one, must not be able to publish.
     */
    public function test_an_unverified_account_cannot_contribute_content(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'dr-ana', 'is_published' => true]);
        $unverified = User::factory()->create([
            'role' => UserRole::Member,
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($unverified);

        $this->postJson("/api/v1/doctors/{$doctor->slug}/reviews", [
            'rating' => 5,
            'body' => 'Excellent care and clear communication throughout.',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.email_unverified');
    }

    public function test_login_succeeds_once_verified(): void
    {
        User::factory()->create([
            'email' => 'done@example.com',
            'password' => 'sufficiently1long',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'done@example.com',
            'password' => 'sufficiently1long',
        ])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_resend_still_works_when_registration_is_disabled(): void
    {
        Notification::fake();

        $pending = User::factory()->create([
            'email' => 'stranded@example.com',
            'email_verified_at' => null,
        ]);
        SiteSetting::current()->update(['registrations_enabled' => false]);

        // Otherwise this account is stranded: it cannot log in (unverified) and
        // cannot get a new link (registration closed).
        $this->postJson('/api/v1/auth/email/resend', ['email' => 'stranded@example.com'])
            ->assertStatus(202);

        Notification::assertSentTo($pending, VerifyEmailNotification::class);
    }

    public function test_registration_is_blocked_when_disabled(): void
    {
        SiteSetting::current()->update(['registrations_enabled' => false]);

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'blocked@example.com']))
            ->assertForbidden()
            ->assertJsonPath('code', 'registration.disabled');

        $this->assertNull(User::query()->where('email', 'blocked@example.com')->first());
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }
}
