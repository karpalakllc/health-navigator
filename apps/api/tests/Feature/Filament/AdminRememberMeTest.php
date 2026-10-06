<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The admin panel has no "remember me".
 *
 * A remember cookie lives for 400 days and signs its holder back in once the
 * 60-minute session has expired — without the password, and without the second
 * factor, which Filament only asks for on the login form. So the form does not
 * offer it, and a remember cookie that already exists is not honoured.
 */
class AdminRememberMeTest extends TestCase
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

    private function administrator(?string $secret = null): User
    {
        $admin = User::factory()->create([
            'password' => self::PASSWORD,
            'role' => UserRole::Admin,
            'user_kind' => UserKind::Staff,
            'app_authentication_secret' => $secret,
        ]);
        $admin->syncRoles(['Administrator']);

        return $admin;
    }

    private function recallerName(): string
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');

        return $guard->getRecallerName();
    }

    public function test_the_login_form_has_no_remember_field(): void
    {
        Livewire::test(Login::class)
            ->assertFormFieldExists('password')
            ->assertFormFieldDoesNotExist('remember');

        $this->get('/admin/login')->assertOk()->assertDontSee('data.remember', false);
    }

    public function test_asking_to_be_remembered_does_not_issue_a_remember_cookie(): void
    {
        $admin = $this->administrator();
        $tokenBefore = $admin->getRememberToken();

        Livewire::test(Login::class)
            ->set('data.email', $admin->email)
            ->set('data.password', self::PASSWORD)
            ->set('data.remember', true)
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(Cookie::hasQueued($this->recallerName()));
        $this->assertSame($tokenBefore, $admin->refresh()->getRememberToken());
    }

    public function test_an_existing_remember_cookie_does_not_sign_anyone_in(): void
    {
        $admin = $this->administrator(AppAuthentication::make()->generateSecret());
        $admin->forceFill(['remember_token' => 'issued-before-the-form-lost-the-checkbox'])->save();

        $this->withCookie(
            $this->recallerName(),
            $admin->getKey().'|issued-before-the-form-lost-the-checkbox|'.$admin->getAuthPassword(),
        )
            ->get('/admin')
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }
}
