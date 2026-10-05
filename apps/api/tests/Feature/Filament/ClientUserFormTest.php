<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Enums\UserRole;
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

        $editor = User::factory()->create(['role' => UserRole::Moderator, 'user_kind' => UserKind::Staff]);
        $editor->syncRoles(['Client Editor']);

        return $editor;
    }

    private function client(): User
    {
        return User::factory()->create(['role' => UserRole::Member, 'user_kind' => UserKind::Client]);
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
        $admin = User::factory()->create(['role' => UserRole::Admin, 'user_kind' => UserKind::Staff]);
        $admin->syncRoles(['Administrator']);
        $this->actingAs($admin);

        Livewire::test(EditClientUser::class, ['record' => $this->client()->getKey()])
            ->assertActionDoesNotExist('delete');
    }
}
