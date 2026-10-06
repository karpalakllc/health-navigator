<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\TriageController;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\TriageSession;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TriageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CountingHasher;
use Tests\TestCase;

/**
 * Regressions for the Part I security findings: account enumeration on password
 * reset (M1), the optional-auth middleware bypassing Sanctum's token checks
 * (M2), and triage sessions having no ownership binding (L4).
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->forgetRateLimits();
    }

    // ---- M1: password reset must not confirm whether an account exists ----

    public function test_password_reset_response_is_identical_for_known_and_unknown_emails(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.com']);
        $this->forgetRateLimits();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertOk();
        $unknown->assertOk();

        $this->assertSame(
            $known->json(),
            $unknown->json(),
            'The response differs between a registered and an unregistered address, '
            .'which makes the endpoint an account-existence oracle.',
        );
    }

    public function test_password_reset_still_sends_mail_to_a_real_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'real@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'real@example.com'])
            ->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_reset_sends_nothing_for_an_unknown_address(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.com'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    // ---- completing a reset must not confirm whether an account exists either ----

    /**
     * The broker answers "no such account" and "bad token" with different
     * messages (mk: "Испративме линк…" vs "Линкот … невалиден"), so a made-up
     * token used to tell whether an address is registered.
     */
    public function test_reset_rejection_is_identical_for_unknown_email_and_bad_token(): void
    {
        $user = User::factory()->create(['email' => 'known@example.com']);
        Password::createToken($user);

        $payload = fn (string $email): array => [
            'email' => $email,
            'token' => str_repeat('a', 64),
            'password' => 'brand1newpassword',
            'password_confirmation' => 'brand1newpassword',
        ];

        $unknown = $this->postJson('/api/v1/auth/reset-password', $payload('nobody@example.com'));
        $badToken = $this->postJson('/api/v1/auth/reset-password', $payload('known@example.com'));

        $unknown->assertUnprocessable();
        $badToken->assertUnprocessable();
        $this->assertSame($badToken->json(), $unknown->json());
        $this->assertSame([__('passwords.token')], $unknown->json('errors.email'));
    }

    /**
     * Each rejected reset pays for exactly one bcrypt check, whether the broker
     * hashed (a real account holding a live token row) or returned early.
     *
     * @return array<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function rejectedResetCases(): array
    {
        return [
            'unknown address' => ['nobody@example.com', false, false],
            'account without a token row' => ['known@example.com', false, false],
            'account with a live token row' => ['known@example.com', true, false],
            'account with an expired token row' => ['known@example.com', true, true],
        ];
    }

    #[DataProvider('rejectedResetCases')]
    public function test_rejected_reset_pays_for_exactly_one_hash(string $email, bool $withToken, bool $expired): void
    {
        $user = User::factory()->create(['email' => 'known@example.com']);

        if ($withToken) {
            Password::createToken($user);
        }

        if ($expired) {
            $this->travel(2)->hours();
        }

        $hasher = new CountingHasher(app('hash'));
        Hash::swap($hasher);
        app()->forgetInstance('auth.password');
        Password::clearResolvedInstances();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $email,
            'token' => str_repeat('a', 64),
            'password' => 'brand1newpassword',
            'password_confirmation' => 'brand1newpassword',
        ])->assertUnprocessable();

        $this->assertSame(1, $hasher->calls);
    }

    // ---- M2: expired tokens must not authenticate ----

    public function test_expired_token_is_rejected_on_a_guarded_route(): void
    {
        config(['sanctum.expiration' => 60]);

        $user = User::factory()->create();
        $token = $this->agedToken($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    /**
     * The route that actually uses `auth.sanctum.optional`.
     *
     * The previous version of this test pointed at /me, which is `auth:sanctum` —
     * so it proved the Sanctum guard rejects expired tokens (which was never in
     * doubt) and nothing at all about the middleware it was named after. That
     * middleware used to resolve tokens with PersonalAccessToken::findToken(),
     * which skips expiry entirely; nothing here would have caught a regression.
     */
    public function test_expired_token_is_ignored_by_optional_auth(): void
    {
        config(['sanctum.expiration' => 60]);

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $moderator = User::factory()->create();
        $moderator->assignRole('Forum Moderator');

        $category = ForumCategory::factory()->create(['is_published' => true]);
        $topic = ForumTopic::factory()->create(['forum_category_id' => $category->id]);

        $fresh = $moderator->createToken('fresh')->plainTextToken;
        $path = "/api/v1/forum/categories/{$category->slug}/topics/{$topic->slug}";

        // A live token resolves the viewer, so the moderation flag is present.
        $this->withHeader('Authorization', "Bearer {$fresh}")
            ->getJson($path)
            ->assertOk()
            ->assertJsonPath('data.topic.viewer.can_moderate', true);

        $this->app['auth']->forgetGuards();

        // An expired one must be ignored entirely — the response is the anonymous one.
        $this->withHeader('Authorization', 'Bearer '.$this->agedToken($moderator))
            ->getJson($path)
            ->assertOk()
            ->assertJsonMissingPath('data.topic.viewer');
    }

    /** Issue a token already older than the configured expiry window. */
    private function agedToken(User $user): string
    {
        $token = $user->createToken('aged');

        $token->accessToken->forceFill([
            'created_at' => Carbon::now()->subMinutes(120),
        ])->save();

        return $token->plainTextToken;
    }

    // ---- L4: a session is only reachable by whoever holds its secret ----
    // (Sessions are no longer linked to accounts — owner decision 2026-10-06 —
    // so the binding is the per-session token, not the user id.)

    public function test_a_session_cannot_be_answered_or_completed_without_its_token(): void
    {
        $this->seed(TriageSeeder::class);

        $started = $this->postJson('/api/v1/triage/sessions', ['accepted_terms' => true])
            ->assertCreated();
        $sessionId = $started->json('data.session_id');
        $token = $started->json('data.session_token');

        $this->assertIsString($token);
        $this->assertGreaterThanOrEqual(64, strlen($token));

        // Knowing the id is not enough: no token, or someone else's token.
        $other = $this->postJson('/api/v1/triage/sessions', ['accepted_terms' => true])
            ->json('data.session_token');

        foreach ([[], [TriageController::TOKEN_HEADER => $other]] as $headers) {
            $this->withHeaders($headers)
                ->putJson("/api/v1/triage/sessions/{$sessionId}/answers", [
                    'answers' => [['step_key' => 'red_flags', 'values' => []]],
                ])->assertNotFound();
            $this->withHeaders($headers)
                ->postJson("/api/v1/triage/sessions/{$sessionId}/complete")
                ->assertNotFound();
            $this->withHeaders($headers)
                ->postJson("/api/v1/triage/sessions/{$sessionId}/emergency")
                ->assertNotFound();
        }

        $this->assertFalse(TriageSession::query()->findOrFail($sessionId)->answers()->exists());

        // Only the hash is stored.
        $stored = TriageSession::query()->findOrFail($sessionId)->getAttribute('token_hash');
        $this->assertNotSame($token, $stored);
        $this->assertSame(hash('sha256', $token), $stored);

        $this->withHeader(TriageController::TOKEN_HEADER, $token)
            ->postJson("/api/v1/triage/sessions/{$sessionId}/emergency")
            ->assertOk();
    }

    public function test_a_signed_in_users_session_is_not_linked_to_the_account(): void
    {
        $this->seed(TriageSeeder::class);

        $user = User::factory()->create();
        $bearer = $user->createToken('web')->plainTextToken;

        $started = $this->withHeader('Authorization', "Bearer {$bearer}")
            ->postJson('/api/v1/triage/sessions', ['accepted_terms' => true])
            ->assertCreated();

        $this->assertFalse(Schema::hasColumn('triage_sessions', 'user_id'));
        $this->assertArrayNotHasKey('user_id', TriageSession::query()->findOrFail($started->json('data.session_id'))->getAttributes());

        // Signed in or not, the token is what continues the session.
        $this->withHeader(TriageController::TOKEN_HEADER, $started->json('data.session_token'))
            ->postJson('/api/v1/triage/sessions/'.$started->json('data.session_id').'/emergency')
            ->assertOk();
    }

    public function test_the_migration_drops_existing_links_to_accounts(): void
    {
        $files = glob(database_path('migrations/*_unlink_triage_sessions_from_users.php')) ?: [];
        $this->assertCount(1, $files);
        $migration = require $files[0];

        $migration->down();
        $this->seed(TriageSeeder::class);

        $user = User::factory()->create();
        $id = (string) Str::uuid();
        DB::table('triage_sessions')->insert([
            'id' => $id,
            'triage_flow_id' => DB::table('triage_flows')->value('id'),
            'user_id' => $user->id,
            'terms_accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('triage_sessions', 'user_id'));
        $this->assertTrue(DB::table('triage_sessions')->where('id', $id)->exists());

        // A pre-migration session has no secret, so it cannot be continued.
        $this->postJson("/api/v1/triage/sessions/{$id}/emergency")->assertNotFound();
    }

    public function test_a_token_issued_before_gaining_panel_access_stops_working(): void
    {
        // Staff must use the 2FA-protected panel; API login refuses them. A token
        // the account held from before it was granted panel access must not
        // become a way around that.
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $user->assignRole('Moderator');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
