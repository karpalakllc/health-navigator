<?php

namespace Tests\Feature\Api\V1\Usernames;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Existing accounts get a temporary username and are asked to choose their
 * own; deleted accounts get none.
 */
class UsernameMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_members_get_a_unique_temporary_username_to_replace(): void
    {
        $files = glob(database_path('migrations/*_add_usernames_to_users.php')) ?: [];
        $this->assertCount(1, $files);
        $migration = require $files[0];

        // The later unique index on the skeleton goes first, as a rollback would.
        (require database_path('migrations/2026_10_14_150002_make_username_skeleton_unique.php'))->down();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'username'));

        $ids = [];
        foreach (['Марија Костовска', 'Петар Николовски', 'Ана Петрова'] as $i => $name) {
            $ids[] = DB::table('users')->insertGetId([
                'name' => $name,
                'display_name' => mb_substr($name, 0, 5).'.',
                'email' => "legacy{$i}@example.com",
                'password' => 'x',
                'user_kind' => 'client',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $deleted = DB::table('users')->insertGetId([
            'name' => '',
            'email' => 'deleted-9-x@deleted.invalid',
            'password' => 'x',
            'user_kind' => 'client',
            'anonymised_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $rows = DB::table('users')->whereIn('id', $ids)->get();
        $this->assertCount(3, $rows->pluck('username')->unique());

        foreach ($rows as $row) {
            $this->assertMatchesRegularExpression('/^clen-[a-z2689]{6}$/', $row->username);
            $this->assertSame(str_replace('-', '', $row->username), $row->username_normalized);
            $this->assertTrue((bool) $row->must_choose_username);
            $this->assertNull($row->terms_accepted_at);
        }

        $this->assertNull(DB::table('users')->where('id', $deleted)->value('username'));

        // Running the data step again changes nothing (idempotent): the column
        // exists now, so only rows without a username would be filled.
        $this->assertSame(0, DB::table('users')->whereNull('username')->whereNull('anonymised_at')->count());
    }
}
