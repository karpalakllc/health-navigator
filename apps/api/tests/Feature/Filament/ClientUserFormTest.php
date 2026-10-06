<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Clients\Pages\EditClientUser;
use App\Models\ForumCategory;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientUserFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
    }

    private function clientEditor(): User
    {
        Role::findOrCreate('Client Editor', 'web')
            ->syncPermissions(['admin.access', 'clients.view', 'clients.update']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $editor = User::factory()->staff()->create();
        $editor->syncRoles(['Client Editor']);

        return $editor;
    }

    private function client(): User
    {
        return User::factory()->create();
    }

    public function test_moderation_scope_requires_the_assign_roles_permission(): void
    {
        $client = $this->client();
        $category = ForumCategory::factory()->create();
        $client->moderatedForumCategories()->attach($category);

        $this->actingAs($this->clientEditor());

        Livewire::test(EditClientUser::class, ['record' => $client->getKey()])
            ->assertFormFieldHidden('roles')
            ->assertFormFieldHidden('moderatedForumCategories')
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$category->getKey()], $client->moderatedForumCategories()->pluck('forum_categories.id')->all());
    }

    public function test_an_assigner_sees_the_moderation_scope(): void
    {
        $editor = $this->clientEditor();
        $editor->givePermissionTo('clients.assign_roles');
        $this->actingAs($editor);

        Livewire::test(EditClientUser::class, ['record' => $this->client()->getKey()])
            ->assertFormFieldVisible('moderatedForumCategories');
    }

    public function test_the_client_page_offers_no_delete_action(): void
    {
        $admin = User::factory()->staff()->create();
        $admin->syncRoles(['Administrator']);
        $this->actingAs($admin);

        Livewire::test(EditClientUser::class, ['record' => $this->client()->getKey()])
            ->assertActionDoesNotExist('delete');
    }

    public function test_an_assigner_cannot_grant_a_community_role_with_permissions_they_lack(): void
    {
        // clients.assign_roles, but none of the forum moderation permissions the
        // Forum Moderator role carries.
        $editor = $this->clientEditor();
        $editor->givePermissionTo('clients.assign_roles');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($editor);

        $client = $this->client();

        Livewire::test(EditClientUser::class, ['record' => $client->getKey()])
            ->fillForm(['roles' => [Role::findByName('Forum Moderator', 'web')->getKey()]])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $this->assertFalse($client->fresh()->hasRole('Forum Moderator'));
    }

    public function test_an_administrator_can_still_grant_the_community_role(): void
    {
        $admin = User::factory()->staff()->create();
        $admin->syncRoles(['Administrator']);
        $this->actingAs($admin);

        $client = $this->client();

        Livewire::test(EditClientUser::class, ['record' => $client->getKey()])
            ->fillForm(['roles' => [Role::findByName('Forum Moderator', 'web')->getKey()]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($client->fresh()->hasRole('Forum Moderator'));
    }
}
