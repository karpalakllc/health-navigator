<?php

use App\Support\TrigramSearchIndexes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GIN trigram indexes for the columns ScriptInsensitiveSearch filters with
 * ILIKE '%term%'. A btree cannot serve a leading wildcard, so every directory
 * search was a sequential scan; pg_trgm turns each Cyrillic/Latin variant into
 * a bitmap index scan, OR-ed together.
 *
 * PostgreSQL only — SQLite has no equivalent and its tables stay small.
 *
 * pg_trgm is a "trusted" extension (PostgreSQL 13+), so the database owner can
 * create it without superuser. A managed host that does not allow it must not
 * block the deploy: searches still work, just unindexed, and
 * `platform:preflight` warns until the extension is enabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! $this->ensureExtension()) {
            Log::warning('pg_trgm is not available; skipping trigram search indexes. Search falls back to sequential scans.');

            return;
        }

        foreach (TrigramSearchIndexes::COLUMNS as $index => [$table, $column]) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$index} ON {$table} USING gin ({$column} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys(TrigramSearchIndexes::COLUMNS) as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        // The extension is left installed: other objects may depend on it.
    }

    private function ensureExtension(): bool
    {
        if (TrigramSearchIndexes::extensionInstalled()) {
            return true;
        }

        try {
            // A savepoint, so a refused CREATE EXTENSION does not abort the
            // migration's surrounding transaction.
            DB::transaction(fn () => DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm'));
        } catch (Throwable $exception) {
            Log::warning('Could not create the pg_trgm extension: '.$exception->getMessage());

            return false;
        }

        return true;
    }
};
