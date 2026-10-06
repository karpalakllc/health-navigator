<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The 2026-10-10 display-name backfill. Display names are no longer shown
 * anywhere (usernames replaced them, tests/Feature/Api/V1/Usernames); the
 * column stays until a later release drops it, so its migration must still
 * run in both directions.
 */
class DisplayNameTest extends TestCase
{
    use RefreshDatabase;

    // ---- backfill ----

    public function test_the_migration_backfills_first_name_and_last_initial(): void
    {
        $files = glob(database_path('migrations/*_add_display_name_to_users.php')) ?: [];
        $this->assertCount(1, $files);
        $migration = require $files[0];

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'display_name'));

        $ids = [];
        foreach (['Марија Костовска', 'Ана Марија Петровска', 'Бојан', '  ана   стојанова '] as $i => $name) {
            $ids[$name] = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => "legacy{$i}@example.com",
                'password' => 'x',
                'user_kind' => 'client',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration->up();

        $this->assertSame([
            'Марија Костовска' => 'Марија К.',
            'Ана Марија Петровска' => 'Ана П.',
            'Бојан' => 'Бојан',
            '  ана   стојанова ' => 'ана С.',
        ], array_map(
            fn (int $id): ?string => DB::table('users')->where('id', $id)->value('display_name'),
            $ids,
        ));
    }
}
