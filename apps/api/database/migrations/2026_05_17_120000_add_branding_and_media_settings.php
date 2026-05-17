<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('public_forum');
            $table->string('favicon_path')->nullable()->after('logo_path');
            $table->text('footer_emergency_text')->nullable()->after('favicon_path');
            $table->text('footer_disclaimer_text')->nullable()->after('footer_emergency_text');
            $table->string('copyright_name')->default('Zdravje360')->after('footer_disclaimer_text');
            $table->unsignedSmallInteger('profile_avatar_min_messages')->default(10)->after('copyright_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'favicon_path',
                'footer_emergency_text',
                'footer_disclaimer_text',
                'copyright_name',
                'profile_avatar_min_messages',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
