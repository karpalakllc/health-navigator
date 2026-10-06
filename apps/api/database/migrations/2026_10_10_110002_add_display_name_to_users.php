<?php

use App\Support\DisplayName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A separate public name, so reviews and forum posts stop showing the full
 * name people registered with (owner decision 2026-10-06).
 *
 * Existing accounts get "first word + last initial." ("Марија Костовска" →
 * "Марија К."); they can change it on the account page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('display_name', DisplayName::MAX_LENGTH)->nullable()->after('name');
        });

        DB::table('users')
            ->whereNull('display_name')
            ->select(['id', 'name'])
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $suggested = DisplayName::suggest((string) $user->name);

                    DB::table('users')->where('id', $user->id)->update([
                        'display_name' => $suggested === '' ? null : $suggested,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
