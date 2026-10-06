<?php

namespace Tests\Feature\Api\V1;

use App\Filament\Resources\Clients\Pages\EditClientUser;
use App\Filament\Resources\Clients\Pages\ListClientUsers;
use App\Models\Doctor;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * D7: staff can suspend a client with a reason; a suspended account cannot sign
 * in, its existing tokens stop working at once, and lifting the suspension
 * restores them. Content is untouched.
 */
class SuspensionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'sufficiently1long';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        $this->forgetRateLimits();
    }

    private function member(): User
    {
        return User::factory()->create([
            'email' => 'member@example.com',
            'password' => self::PASSWORD,
        ]);
    }

    private function suspend(User $user): void
    {
        $user->suspend(User::factory()->admin()->create(), 'Spam in the forum');
    }

    private function getMe(string $token): \Illuminate\Testing\TestResponse
    {
        // Each call is a fresh request: the guard must not reuse a user resolved earlier.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/v1/me');
    }

    public function test_a_suspended_member_with_the_right_password_is_refused_without_a_token(): void
    {
        $member = $this->member();
        $this->suspend($member);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.com',
            'password' => self::PASSWORD,
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.account_suspended')
            ->assertJsonMissingPath('data.token')
            // The staff-only reason never reaches the member.
            ->assertDontSee('Spam in the forum');

        $this->assertSame(0, $member->tokens()->count());
    }

    public function test_a_wrong_password_on_a_suspended_account_reads_like_any_wrong_password(): void
    {
        $member = $this->member();
        $this->suspend($member);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.com',
            'password' => 'not-the-password1',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('api.auth.invalid_credentials'));
    }

    public function test_existing_tokens_stop_working_at_once_and_return_after_unsuspending(): void
    {
        $member = $this->member();
        $token = $member->createToken('Firefox · Linux')->plainTextToken;

        $this->getMe($token)->assertOk();

        $this->suspend($member);

        $this->getMe($token)->assertUnauthorized();
        // Refused, not deleted.
        $this->assertSame(1, $member->tokens()->count());

        $member->fresh()->unsuspend();

        $this->getMe($token)->assertOk();
    }

    public function test_optional_auth_routes_treat_a_suspended_token_as_anonymous(): void
    {
        $member = $this->member();
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);
        Review::factory()->approved()->create([
            'user_id' => $member->id,
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
        ]);
        $token = $member->createToken('web')->plainTextToken;
        $this->suspend($member);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('meta.viewer_review', null)
            // Content stays public: suspension does not moderate it.
            ->assertJsonCount(1, 'data');
    }

    public function test_an_administrator_suspends_with_a_reason_and_lifts_it_in_the_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $member = $this->member();
        $token = $member->createToken('web')->plainTextToken;
        $this->actingAs($admin);

        Livewire::test(EditClientUser::class, ['record' => $member->getKey()])
            ->callAction('suspend', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        $this->assertFalse($member->fresh()->isSuspended());

        Livewire::test(EditClientUser::class, ['record' => $member->getKey()])
            ->callAction('suspend', data: ['reason' => 'Repeated abuse in replies'])
            ->assertHasNoActionErrors();

        $member->refresh();
        $this->assertTrue($member->isSuspended());
        $this->assertSame('Repeated abuse in replies', $member->suspension_reason);
        $this->assertTrue($member->suspendedBy->is($admin));

        $this->getMe($token)->assertUnauthorized();

        $this->actingAs($admin);
        Livewire::test(EditClientUser::class, ['record' => $member->getKey()])
            ->assertActionHidden('suspend')
            ->callAction('unsuspend')
            ->assertHasNoActionErrors();

        $member->refresh();
        $this->assertFalse($member->isSuspended());
        $this->assertNull($member->suspension_reason);
        $this->assertNull($member->suspended_by_id);
    }

    public function test_the_table_action_suspends_too(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $member = $this->member();

        Livewire::test(ListClientUsers::class)
            ->callTableAction('suspend', $member, data: ['reason' => 'Spam links'])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($member->fresh()->isSuspended());
    }

    public function test_staff_without_the_suspend_permission_do_not_get_the_action(): void
    {
        $role = Role::findOrCreate('Client support', 'web');
        $role->syncPermissions(['admin.access', 'clients.view', 'clients.update']);
        $support = User::factory()->staff('Client support')->create();
        $member = $this->member();

        $this->actingAs($support);

        Livewire::test(EditClientUser::class, ['record' => $member->getKey()])
            ->assertActionHidden('suspend');

        $this->assertFalse($support->can('suspend', $member));
    }

    public function test_the_policy_refuses_staff_targets_oneself_and_higher_privileged_clients(): void
    {
        $role = Role::findOrCreate('Client support', 'web');
        $role->syncPermissions(['admin.access', 'clients.view', 'clients.update', 'clients.suspend']);
        $support = User::factory()->staff('Client support')->create();
        $admin = User::factory()->admin()->create();

        $communityModerator = User::factory()->create();
        $communityModerator->assignRole(RoleCatalog::ensure(RoleCatalog::FORUM_MODERATOR));

        $this->assertTrue($support->can('suspend', $this->member()));
        $this->assertFalse($admin->can('suspend', User::factory()->moderator()->create()));
        $this->assertFalse($admin->can('suspend', $admin));
        // The Forum Moderator role carries permissions the support role lacks.
        $this->assertFalse($support->can('suspend', $communityModerator));
        $this->assertTrue($admin->can('suspend', $communityModerator));
    }

    public function test_a_suspended_community_moderator_loses_the_admin_panel(): void
    {
        $moderator = User::factory()->create();
        $moderator->assignRole(RoleCatalog::ensure(RoleCatalog::FORUM_MODERATOR));

        $panel = Filament::getPanel('admin');
        $this->assertTrue($moderator->canAccessPanel($panel));

        $this->suspend($moderator);

        $this->assertFalse($moderator->fresh()->canAccessPanel($panel));
    }

    public function test_the_administrator_role_holds_the_suspend_permission(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->can('clients.suspend'));
        $this->assertFalse(User::factory()->moderator()->create()->can('clients.suspend'));
    }
}
