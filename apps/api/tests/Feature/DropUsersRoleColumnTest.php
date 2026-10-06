<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The legacy `users.role` column is gone (2026_10_13_100000), nothing depends on
 * it, and the migration can be rolled back to the deprecated state.
 */
class DropUsersRoleColumnTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_13_100000_drop_role_from_users_table.php';

    public function test_the_column_is_dropped(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'role'));
    }

    public function test_accounts_still_work_and_report_a_derived_role_without_the_column(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $member = User::factory()->create();
        $member->syncRoles([RoleCatalog::MEMBER]);
        Sanctum::actingAs($member);

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user.role', 'member');
        $this->assertArrayNotHasKey('role', $member->fresh()->getAttributes());
    }

    public function test_rolling_back_restores_the_values_the_old_code_stored(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->staff()->create();
        $admin->syncRoles([RoleCatalog::ADMINISTRATOR]);
        $moderator = User::factory()->staff()->create();
        $moderator->syncRoles([RoleCatalog::MODERATOR]);
        $member = User::factory()->create();

        $migration = require database_path(self::MIGRATION);
        $migration->down();

        $this->assertTrue(Schema::hasColumn('users', 'role'));
        $this->assertSame('admin', DB::table('users')->where('id', $admin->id)->value('role'));
        $this->assertSame('moderator', DB::table('users')->where('id', $moderator->id)->value('role'));
        $this->assertSame('member', DB::table('users')->where('id', $member->id)->value('role'));

        // The deprecation migration's own rollback (restore NOT NULL) still runs.
        $deprecate = require database_path('migrations/2026_10_10_100001_deprecate_users_role_column.php');
        $deprecate->down();
        $deprecate->up();

        $migration->up();
        $this->assertFalse(Schema::hasColumn('users', 'role'));
    }
}
