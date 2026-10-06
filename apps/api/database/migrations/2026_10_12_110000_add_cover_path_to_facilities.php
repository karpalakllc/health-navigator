<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A wide header image for facility and pharmacy profiles, stored on the media
 * disk like avatar_url (which stays the logo). A disk path, never a URL: the
 * API resolves it through MediaUrl.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });
    }
};
