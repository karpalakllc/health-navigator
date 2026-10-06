<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Staff two-factor authentication in the admin panel (Filament's
 * AppAuthentication with recovery codes).
 *
 * Required for anyone holding admin.access, optional for community moderators,
 * and not a way round the API: an account behind a second factor cannot mint a
 * token with its password alone.
 */
class StaffMultiFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private function administrator(?string $secret = null, array $recoveryCodes = []): User
    {
        $admin = User::factory()->create([
            'password' => self::PASSWORD,
            'role' => UserRole::Admin,
            'user_kind' => UserKind::Staff,
            'app_authentication_secret' => $secret,
            'app_authentication_recovery_codes' => $recoveryCodes === []
                ? null
                : array_map(fn (string $code): string => Hash::make($code), $recoveryCodes),
        ]);
        $admin->syncRoles(['Administrator']);

        return $admin;
    }

    private function communityModerator(?string $secret = null): User
    {
        $moderator = User::factory()->create([
            'password' => self::PASSWORD,
            'role' => UserRole::Member,
            'user_kind' => UserKind::Client,
            'app_authentication_secret' => $secret,
        ]);
        $moderator->assignRole('Forum Moderator');

        return $moderator;
    }

    private function secret(): string
    {
        return AppAuthentication::make()->generateSecret();
    }

    private function currentCode(User $user, string $secret): string
    {
        return AppAuthentication::make()->getCurrentCode($user, $secret);
    }

    private function setUpUrl(): string
    {
        return route('filament.admin.auth.multi-factor-authentication.set-up-required');
    }

    // --- enforcement -------------------------------------------------------

    public function test_an_administrator_without_mfa_is_sent_to_set_it_up(): void
    {
        $this->actingAs($this->administrator());

        $this->get('/admin')->assertRedirect($this->setUpUrl());
        $this->get('/admin/staff/staff-users')->assertRedirect($this->setUpUrl());
        $this->get('/admin/profile')->assertRedirect($this->setUpUrl());

        // The set-up page itself must stay reachable, or the redirect loops.
        $this->get($this->setUpUrl())->assertOk();
    }

    public function test_a_moderator_holding_admin_access_is_held_to_it_too(): void
    {
        $moderator = User::factory()->moderator()->create();
        $moderator->syncRoles(['Moderator']);

        $this->actingAs($moderator)->get('/admin')->assertRedirect($this->setUpUrl());
    }

    public function test_an_enrolled_administrator_reaches_the_panel(): void
    {
        $this->actingAs($this->administrator($this->secret()));

        $this->get('/admin')->assertOk();
        $this->get('/admin/profile')->assertOk()->assertSee('regenerateAppAuthenticationRecoveryCodes', false);
    }

    public function test_mfa_is_optional_for_a_community_moderator(): void
    {
        $this->actingAs($this->communityModerator());

        $this->get('/admin')->assertOk();
        // ...and they can enrol from their profile if they choose to.
        $this->get('/admin/profile')->assertOk()->assertSee('setUpAppAuthentication', false);
    }

    // --- sign-in -----------------------------------------------------------

    public function test_signing_in_without_mfa_when_required_ends_on_the_set_up_page(): void
    {
        $admin = $this->administrator();

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertRedirect($this->setUpUrl());
    }

    public function test_signing_in_with_mfa_asks_for_a_code_before_logging_in(): void
    {
        $secret = $this->secret();
        $admin = $this->administrator($secret);

        $login = Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        // Password accepted, but no session yet: the challenge is showing.
        $this->assertGuest();
        $this->assertNotNull($login->get('userUndertakingMultiFactorAuthentication'));

        $login->set('data.multiFactor.app.code', $this->currentCode($admin, $secret))
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $secret = $this->secret();
        $admin = $this->administrator($secret);
        $wrong = $this->currentCode($admin, $secret) === '000000' ? '111111' : '000000';

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => self::PASSWORD])
            ->call('authenticate')
            ->set('data.multiFactor.app.code', $wrong)
            ->call('authenticate')
            ->assertHasErrors(['data.multiFactor.app.code']);

        $this->assertGuest();
    }

    public function test_a_recovery_code_signs_in_once(): void
    {
        $admin = $this->administrator($this->secret(), ['first-recovery-code', 'second-recovery-code']);

        $signInWithRecoveryCode = fn () => Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => self::PASSWORD])
            ->call('authenticate')
            ->set('data.multiFactor.app.useRecoveryCode', true)
            ->set('data.multiFactor.app.recoveryCode', 'first-recovery-code')
            ->call('authenticate');

        $signInWithRecoveryCode()->assertHasNoErrors();
        $this->assertAuthenticatedAs($admin);
        $this->assertCount(1, $admin->refresh()->getAppAuthenticationRecoveryCodes());

        auth()->logout();

        $signInWithRecoveryCode()->assertHasErrors(['data.multiFactor.app.recoveryCode']);
        $this->assertGuest();
    }

    // --- management --------------------------------------------------------

    public function test_setting_up_requires_the_current_password_and_stores_the_secret_encrypted(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin);

        $page = Livewire::test(EditProfile::class)
            ->mountAction(TestAction::make('setUpAppAuthentication')->schemaComponent('app', schema: 'content'));

        $secret = decrypt($page->instance()->mountedActions[0]['arguments']['encrypted'])['secret'];
        $code = $this->currentCode($admin, $secret);

        $page->setActionData(['code' => $code, 'password' => 'not-my-password'])
            ->callMountedAction()
            ->assertHasActionErrors(['password']);
        $this->assertFalse($admin->refresh()->hasMultiFactorAuthenticationEnabled());

        $page->setActionData(['code' => $code, 'password' => self::PASSWORD])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $admin->refresh();
        $this->assertSame($secret, $admin->getAppAuthenticationSecret());
        $this->assertCount(8, $admin->getAppAuthenticationRecoveryCodes());

        // At rest: ciphertext, not the base32 secret an authenticator app shows.
        $stored = DB::table('users')->where('id', $admin->getKey())->first();
        $this->assertStringNotContainsString($secret, (string) $stored->app_authentication_secret);
        $this->assertSame($secret, decrypt($stored->app_authentication_secret, unserialize: false));
    }

    public function test_regenerating_recovery_codes_requires_the_current_password(): void
    {
        $admin = $this->administrator($this->secret(), ['old-recovery-code']);
        $before = $admin->getAppAuthenticationRecoveryCodes();
        $this->actingAs($admin);

        $action = TestAction::make('regenerateAppAuthenticationRecoveryCodes')->schemaComponent('app', schema: 'content');

        Livewire::test(EditProfile::class)
            ->callAction($action, ['password' => ''])
            ->assertHasActionErrors(['password']);

        Livewire::test(EditProfile::class)
            ->callAction($action, ['password' => 'not-my-password'])
            ->assertHasActionErrors(['password']);

        $this->assertSame($before, $admin->refresh()->getAppAuthenticationRecoveryCodes());

        Livewire::test(EditProfile::class)
            ->callAction($action, ['password' => self::PASSWORD])
            ->assertHasNoActionErrors();

        $after = $admin->refresh()->getAppAuthenticationRecoveryCodes();
        $this->assertCount(8, $after);
        $this->assertNotSame($before, $after);
    }

    public function test_the_profile_page_cannot_edit_the_account(): void
    {
        $admin = $this->administrator($this->secret());
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)
            ->set('data.email', 'attacker@example.com')
            ->set('data.name', 'Renamed')
            ->call('save');

        $this->assertSame($admin->email, $admin->refresh()->email);
        $this->assertNotSame('Renamed', $admin->name);
    }

    // --- secrecy -----------------------------------------------------------

    public function test_the_secret_and_recovery_codes_are_never_serialised(): void
    {
        $secret = $this->secret();
        $moderator = $this->communityModerator($secret);
        $moderator->saveAppAuthenticationRecoveryCodes([Hash::make('a-recovery-code')]);

        $array = $moderator->refresh()->toArray();
        $this->assertArrayNotHasKey('app_authentication_secret', $array);
        $this->assertArrayNotHasKey('app_authentication_recovery_codes', $array);

        Sanctum::actingAs($moderator);
        $response = $this->getJson('/api/v1/me')->assertOk();

        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertStringNotContainsString('app_authentication', $response->getContent());
    }

    // --- the API -----------------------------------------------------------

    public function test_staff_cannot_get_an_api_token_with_their_password(): void
    {
        foreach ([$this->administrator(), $this->administrator($this->secret())] as $admin) {
            $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => self::PASSWORD])
                ->assertForbidden()
                ->assertJsonPath('code', 'auth.staff_use_admin')
                ->assertJsonMissingPath('data.token');

            $this->assertSame(0, $admin->tokens()->count());
        }
    }

    public function test_an_enrolled_community_moderator_cannot_get_an_api_token_with_their_password(): void
    {
        $moderator = $this->communityModerator($this->secret());

        $this->postJson('/api/v1/auth/login', ['email' => $moderator->email, 'password' => self::PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.staff_use_admin');

        $this->assertSame(0, $moderator->tokens()->count());
    }

    public function test_an_unenrolled_community_moderator_still_signs_in_to_the_api(): void
    {
        $moderator = $this->communityModerator();

        $this->postJson('/api/v1/auth/login', ['email' => $moderator->email, 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_a_wrong_password_still_reads_as_invalid_credentials_for_staff(): void
    {
        $admin = $this->administrator($this->secret());

        $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation.failed');
    }

    public function test_enrolling_revokes_existing_api_tokens(): void
    {
        $moderator = $this->communityModerator();
        $moderator->createToken('web');

        $moderator->saveAppAuthenticationSecret($this->secret());

        $this->assertSame(0, $moderator->tokens()->count());
    }
}
