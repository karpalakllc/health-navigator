<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * The CI PostgreSQL job only proves anything if the suite really runs on
 * PostgreSQL. phpunit.xml's <env> defaults, a stray DB_URL, a .env.testing or
 * a cached config can each quietly put the suite back on SQLite while the job
 * stays green — so assert on the driver the live PDO handle reports.
 */
class DatabaseDriverTest extends TestCase
{
    /** Connection names whose PDO driver name differs from the connection name. */
    private const PDO_DRIVER = [
        'mariadb' => 'mysql',
    ];

    public function test_the_suite_runs_on_the_database_the_environment_asked_for(): void
    {
        $requested = getenv('DB_CONNECTION');

        if ($requested === false || $requested === '') {
            $this->markTestSkipped('DB_CONNECTION is not set; nothing to compare against.');
        }

        $this->assertSame($requested, config('database.default'), 'The default connection is not the one DB_CONNECTION named.');

        $this->assertSame(
            self::PDO_DRIVER[$requested] ?? $requested,
            DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME),
            "DB_CONNECTION={$requested}, but the suite is talking to a different database driver.",
        );
    }
}
