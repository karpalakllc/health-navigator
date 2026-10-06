<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The PostgreSQL pg_trgm GIN indexes behind ScriptInsensitiveSearch's
 * ILIKE '%term%' filters. Shared by the migration that creates them and by
 * platform:preflight, which warns when the extension is missing.
 */
final class TrigramSearchIndexes
{
    /** index name => [table, column] — every column a search scope passes to ScriptInsensitiveSearch. */
    public const COLUMNS = [
        'doctors_full_name_trgm_index' => ['doctors', 'full_name'],
        'doctors_city_trgm_index' => ['doctors', 'city'],
        'facilities_name_trgm_index' => ['facilities', 'name'],
        'facilities_city_trgm_index' => ['facilities', 'city'],
        'products_name_trgm_index' => ['products', 'name'],
        'forum_topics_title_trgm_index' => ['forum_topics', 'title'],
    ];

    public static function extensionInstalled(): bool
    {
        try {
            return DB::table('pg_extension')->where('extname', 'pg_trgm')->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
