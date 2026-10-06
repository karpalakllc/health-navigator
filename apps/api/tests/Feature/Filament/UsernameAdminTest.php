<?php

namespace Tests\Feature\Filament;

use App\Enums\UsernameMatchType;
use App\Enums\UsernameTermKind;
use App\Filament\Resources\Clients\Pages\EditClientUser;
use App\Filament\Resources\Staff\Pages\CreateStaffUser;
use App\Filament\Resources\UsernameTerms\Pages\CreateUsernameTerm;
use App\Filament\Resources\UsernameTerms\Pages\ListUsernameTerms;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\UsernameHistory;
use App\Models\UsernameTerm;
use App\Support\RoleCatalog;
use App\Support\Usernames\UsernameValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The blocked/reserved username lists and the staff rename, both behind
 * usernames.manage (Administrator by default).
 */
class UsernameAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
        Filament::setCurrentPanel('admin');
    }

    public function test_usernames_manage_belongs_to_the_administrator_role_only(): void
    {
        $this->assertTrue(Role::findByName(RoleCatalog::ADMINISTRATOR, 'web')->hasPermissionTo('usernames.manage'));
        $this->assertFalse(Role::findByName(RoleCatalog::MODERATOR, 'web')->hasPermissionTo('usernames.manage'));
    }

    public function test_the_lists_are_staff_editable_and_take_effect_at_once(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertNull(UsernameValidator::problem('kiflichka'));

        Livewire::test(CreateUsernameTerm::class)
            ->fillForm([
                'term' => 'Кифличка',
                'kind' => UsernameTermKind::Blocked->value,
                'match_type' => UsernameMatchType::Contains->value,
                'language' => 'mk',
                'category' => 'profanity',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Written in Cyrillic, refuses the Latin spelling inside a longer name.
        $this->assertSame('not_allowed', UsernameValidator::problem('ana_kiflichka_1'));

        UsernameTerm::query()->where('term', 'кифличка')->sole()->update(['active' => false]);
        $this->assertNull(UsernameValidator::problem('ana_kiflichka_1'));
    }

    public function test_a_short_contains_term_needs_an_explicit_confirmation(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $form = [
            'term' => 'xyz',
            'kind' => UsernameTermKind::Blocked->value,
            'match_type' => UsernameMatchType::Contains->value,
            'language' => 'any',
        ];

        Livewire::test(CreateUsernameTerm::class)
            ->fillForm($form)
            ->call('create')
            ->assertHasFormErrors(['confirm_short_contains']);

        Livewire::test(CreateUsernameTerm::class)
            ->fillForm([...$form, 'confirm_short_contains' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('not_allowed', UsernameValidator::problem('abcxyzdef'));
    }

    public function test_a_moderator_cannot_see_or_edit_the_lists(): void
    {
        $this->actingAs(User::factory()->moderator()->create());

        Livewire::test(ListUsernameTerms::class)->assertForbidden();
    }

    public function test_staff_can_rename_an_offensive_username_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create(['username' => 'slipped_through']);
        $this->actingAs($admin);

        Livewire::test(EditClientUser::class, ['record' => $member->getKey()])
            ->callAction('renameUsername', ['username' => '', 'reason' => 'Навредливо име, пријавено од член.'])
            ->assertHasNoActionErrors();

        $member->refresh();
        $this->assertStringStartsWith('clen-', $member->username);
        $this->assertTrue($member->must_choose_username);
        // A staff rename does not use up the member's own 90-day change.
        $this->assertNull($member->username_changed_at);

        $history = UsernameHistory::query()->sole();
        $this->assertSame('slipped_through', $history->username);
        $this->assertSame('forced', $history->reason);
        $this->assertSame('Навредливо име, пријавено од член.', $history->note);
        $this->assertSame($admin->getKey(), $history->changed_by_id);
        $this->assertTrue($history->reserved_until->isAfter(now()->addMonths(5)));

        // The member cannot take a name back that staff took away.
        $this->assertSame('taken', UsernameValidator::problem('slipped_through', $member));
    }

    public function test_staff_can_rename_to_a_chosen_name_through_the_same_rules(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $member = User::factory()->create(['username' => 'old_name']);

        Livewire::test(EditClientUser::class, ['record' => $member->getKey()])
            ->callAction('renameUsername', ['username' => 'admin_helper', 'reason' => 'Тест на правилата.'])
            ->assertHasActionErrors(['username']);

        Livewire::test(EditClientUser::class, ['record' => $member->getKey()])
            ->callAction('renameUsername', ['username' => 'nova_mara', 'reason' => 'По барање на членот.'])
            ->assertHasNoActionErrors();

        $this->assertSame('nova_mara', $member->fresh()->username);
        $this->assertFalse($member->fresh()->must_choose_username);
    }

    public function test_renaming_needs_usernames_manage(): void
    {
        Role::findOrCreate('Client Editor', 'web')
            ->syncPermissions(['admin.access', 'clients.view', 'clients.update']);
        $editor = User::factory()->staff('Client Editor')->create();
        $member = User::factory()->create(['username' => 'keeps_name']);
        $this->actingAs($editor);

        Livewire::test(EditClientUser::class, ['record' => $member->getKey()])
            ->assertActionHidden('renameUsername');

        $this->assertSame('keeps_name', $member->fresh()->username);
    }

    public function test_a_staff_account_is_created_with_a_checked_username_or_a_temporary_one(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $roleId = Role::findByName(RoleCatalog::MODERATOR, 'web')->getKey();

        $form = [
            'name' => 'Нов Модератор',
            'email' => 'mod2@example.com',
            'roles' => [$roleId],
            'password' => 'long1enough1password',
        ];

        Livewire::test(CreateStaffUser::class)
            ->fillForm([...$form, 'username' => 'Moderator_Marko'])
            ->call('create')
            ->assertHasFormErrors(['username']);

        Livewire::test(CreateStaffUser::class)
            ->fillForm([...$form, 'username' => 'marko_s'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('marko_s', User::query()->where('email', 'mod2@example.com')->sole()->username);

        Livewire::test(CreateStaffUser::class)
            ->fillForm([...$form, 'email' => 'mod3@example.com'])
            ->call('create')
            ->assertHasNoFormErrors();

        $temporary = User::query()->where('email', 'mod3@example.com')->sole();
        $this->assertStringStartsWith('clen-', $temporary->username);
        $this->assertTrue($temporary->must_choose_username);
    }
}
