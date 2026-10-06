<?php

namespace Tests\Feature\Authorization;

use App\Models\Doctor;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Spatie roles and permissions are the only authorization source. The legacy
 * `users.role` column and `users.user_kind` grant nothing on their own.
 *
 * Replaces PlatformRoutesTest, which covered the retired `role:` middleware's
 * allow/deny matrix; the contribution endpoints it guarded are covered here.
 */
class SpatieSingleSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
        $this->forgetRateLimits();
    }

    // ---- contribution endpoints: who may post ----

    public function test_a_client_holding_the_member_role_may_contribute(): void
    {
        $user = $this->client();
        $user->syncRoles([RoleCatalog::MEMBER]);

        $this->assertContributionStatuses($user, 201);
    }

    public function test_a_client_whose_member_role_was_removed_may_not_contribute(): void
    {
        $user = $this->client();
        $user->syncRoles([]);

        $this->assertContributionStatuses($user, 403);
    }

    public function test_staff_may_not_contribute_by_default(): void
    {
        $user = $this->staff('moderator', RoleCatalog::MODERATOR);

        $this->assertContributionStatuses($user, 403);
    }

    public function test_staff_granted_the_member_role_may_contribute(): void
    {
        $user = $this->staff('moderator', RoleCatalog::MODERATOR);
        $user->assignRole(RoleCatalog::MEMBER);

        $this->assertContributionStatuses($user, 201);
    }

    public function test_a_custom_role_with_the_member_permissions_may_contribute(): void
    {
        $role = Role::findOrCreate('Contributor', 'web');
        $role->syncPermissions(['reviews.create', 'forum.post']);
        $user = $this->client();
        $user->syncRoles([$role]);

        $this->assertContributionStatuses($user, 201);
    }

    public function test_the_retired_platform_stubs_are_gone(): void
    {
        Sanctum::actingAs($this->staff('admin', RoleCatalog::ADMINISTRATOR));

        $this->getJson('/api/v1/platform/staff')->assertNotFound();
        $this->getJson('/api/v1/platform/admin')->assertNotFound();
    }

    /**
     * Every client now holds reviews.create and forum.post. Those are rights
     * over the holder's own contributions, so staff who lack them (all staff,
     * by default) must still be able to manage client accounts.
     */
    public function test_member_permissions_do_not_put_clients_beyond_staff_who_manage_them(): void
    {
        $role = Role::findOrCreate('Client Manager', 'web');
        $role->syncPermissions(['admin.access', 'clients.view', 'clients.update']);
        $manager = $this->staff('moderator', 'Client Manager');
        $client = $this->client();
        $client->syncRoles([RoleCatalog::MEMBER]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($manager->fresh()->can('update', $client->fresh()));
    }

    // ---- registration ----

    public function test_registration_assigns_the_member_role(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New Member',
            'email' => 'new@example.com',
            'password' => 'sufficiently1long',
            'password_confirmation' => 'sufficiently1long',
        ])->assertStatus(202);

        $user = User::query()->where('email', 'new@example.com')->sole();

        $this->assertSame([RoleCatalog::MEMBER], $user->getRoleNames()->all());
        $this->assertTrue($user->can('reviews.create'));
        $this->assertTrue($user->can('forum.post'));
    }

    // ---- isAdmin() ----

    public function test_the_legacy_admin_column_alone_does_not_make_an_administrator(): void
    {
        $user = $this->staff('admin');

        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_settings_update_alone_does_not_make_an_administrator(): void
    {
        $role = Role::findOrCreate('Settings editor', 'web');
        $role->syncPermissions(['admin.access', 'settings.view', 'settings.update']);
        $user = $this->staff('moderator');
        $user->syncRoles([$role]);

        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_the_administrator_role_makes_an_administrator_whatever_the_column_says(): void
    {
        $user = $this->staff('moderator', RoleCatalog::ADMINISTRATOR);

        $this->assertTrue($user->fresh()->isAdmin());
    }

    // ---- /me keeps a derived `role` for the web app ----

    public function test_me_reports_the_account_role_derived_from_spatie(): void
    {
        $admin = $this->staff('moderator', RoleCatalog::ADMINISTRATOR);
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user.role', 'admin');

        $moderator = $this->staff('admin', RoleCatalog::MODERATOR);
        Sanctum::actingAs($moderator);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user.role', 'moderator');

        $member = $this->client();
        $member->syncRoles([RoleCatalog::MEMBER]);
        Sanctum::actingAs($member);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user.role', 'member');
    }

    // ---- the seeder no longer rewrites assignments from the column ----

    public function test_reseeding_keeps_manual_role_assignments(): void
    {
        $demoted = $this->staff('admin', RoleCatalog::MODERATOR);
        $promoted = $this->staff('moderator', RoleCatalog::ADMINISTRATOR);
        $client = $this->client();
        $client->syncRoles([]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame([RoleCatalog::MODERATOR], $demoted->fresh()->getRoleNames()->all());
        $this->assertSame([RoleCatalog::ADMINISTRATOR], $promoted->fresh()->getRoleNames()->all());
        $this->assertSame([], $client->fresh()->getRoleNames()->all());
    }

    // ---- the one-time backfill from the legacy column ----

    public function test_the_migration_moves_what_the_column_granted_onto_spatie_roles(): void
    {
        $member = $this->legacyUser('member@example.com', 'member', 'client');
        $admin = $this->legacyUser('admin@example.com', 'admin', 'staff');
        $roleLessModerator = $this->legacyUser('moderator@example.com', 'moderator', 'staff');
        $assignedModerator = $this->legacyUser('assigned@example.com', 'moderator', 'staff');
        $assignedModerator->assignRole(RoleCatalog::FORUM_MODERATOR);

        $migration = require database_path('migrations/2026_10_10_100000_move_account_roles_onto_spatie.php');
        $migration->up();
        // Idempotent: a second run grants nothing twice.
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame([RoleCatalog::MEMBER], $member->fresh()->getRoleNames()->all());
        $this->assertSame([RoleCatalog::ADMINISTRATOR], $admin->fresh()->getRoleNames()->all());
        $this->assertSame([RoleCatalog::MODERATOR], $roleLessModerator->fresh()->getRoleNames()->all());
        $this->assertSame([RoleCatalog::FORUM_MODERATOR], $assignedModerator->fresh()->getRoleNames()->all());
    }

    // ---- helpers ----

    private function assertContributionStatuses(User $user, int $status): void
    {
        $user = $user->fresh();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($user);

        Doctor::factory()->create(['slug' => 'doc-'.$user->id]);
        $category = ForumCategory::factory()->create(['slug' => 'cat-'.$user->id]);
        $topic = ForumTopic::factory()->create([
            'forum_category_id' => $category->id,
            'slug' => 'topic-'.$user->id,
        ]);

        $this->postJson("/api/v1/doctors/doc-{$user->id}/reviews", [
            'rating' => 5,
            'body' => 'A review with enough characters for validation.',
        ])->assertStatus($status);

        $this->postJson("/api/v1/forum/categories/cat-{$user->id}/topics", [
            'title' => 'A new discussion topic',
            'body' => 'The opening post body with enough length.',
            'accepted_community_rules' => true,
        ])->assertStatus($status);

        $this->postJson("/api/v1/forum/categories/cat-{$user->id}/topics/{$topic->slug}/posts", [
            'body' => 'A reply with enough characters for validation.',
        ])->assertStatus($status);
    }

    private function client(): User
    {
        return $this->legacyUser(fake()->unique()->safeEmail(), 'member', 'client');
    }

    private function staff(string $column, ?string $role = null): User
    {
        $user = $this->legacyUser(fake()->unique()->safeEmail(), $column, 'staff');
        $user->syncRoles($role === null ? [] : [$role]);

        return $user;
    }

    /**
     * Written straight to the table so the account carries exactly the legacy
     * column value under test and whatever roles the test gives it — no factory
     * or registration side effects.
     */
    private function legacyUser(string $email, string $column, string $kind): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Legacy '.$column,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $column,
            'user_kind' => $kind,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }
}
