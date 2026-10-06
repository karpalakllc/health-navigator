<?php

use App\Support\Usernames\UsernameTermMatcher;
use App\Support\Usernames\UsernameTermSeedList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The blocked and reserved username lists (App\Models\UsernameTerm), seeded
 * with the shipped terms. Re-running inserts only terms that are missing, so
 * staff edits survive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('username_terms')) {
            Schema::create('username_terms', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 16);
                $table->string('term', 100);
                $table->string('term_normalized', 150)->index();
                $table->string('term_skeleton', 150)->index();
                $table->string('language', 8)->default('any');
                $table->string('match_type', 16);
                $table->string('category', 32)->nullable();
                $table->string('note', 255)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['kind', 'match_type', 'term']);
            });
        }

        $now = now();

        foreach (array_chunk(UsernameTermSeedList::rows(), 200) as $chunk) {
            DB::table('username_terms')->insertOrIgnore(array_map(
                fn (array $row): array => $row + ['active' => true, 'created_at' => $now, 'updated_at' => $now],
                $chunk,
            ));
        }

        UsernameTermMatcher::forget();
    }

    public function down(): void
    {
        Schema::dropIfExists('username_terms');
        UsernameTermMatcher::forget();
    }
};
