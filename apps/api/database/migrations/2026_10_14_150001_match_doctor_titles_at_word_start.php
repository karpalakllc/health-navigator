<?php

use App\Support\Usernames\UsernameTermMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Doctor titles also refuse a username that starts with them before a
 * consonant („drmarko“, „profivanov“): the shipped `exact` rows for them
 * become `prefix` (App\Enums\UsernameMatchType::Prefix), which still matches
 * every username `exact` did. A row staff already added as `prefix` wins;
 * rows staff deleted or deactivated stay as they are.
 */
return new class extends Migration
{
    private const TITLES = ['dr', 'д-р', 'др', 'doc', 'prof', 'проф', 'mjek'];

    public function up(): void
    {
        foreach (self::TITLES as $term) {
            $hasPrefix = DB::table('username_terms')
                ->where('kind', 'reserved')->where('match_type', 'prefix')->where('term', $term)
                ->exists();

            $exact = DB::table('username_terms')
                ->where('kind', 'reserved')->where('match_type', 'exact')->where('term', $term);

            $hasPrefix ? $exact->delete() : $exact->update(['match_type' => 'prefix', 'updated_at' => now()]);
        }

        UsernameTermMatcher::forget();
    }

    public function down(): void
    {
        DB::table('username_terms')
            ->where('kind', 'reserved')->where('match_type', 'prefix')->whereIn('term', self::TITLES)
            ->update(['match_type' => 'exact', 'updated_at' => now()]);

        UsernameTermMatcher::forget();
    }
};
