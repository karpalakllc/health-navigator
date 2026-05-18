<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->text('maintenance_message')->nullable()->after('maintenance_mode');
            $table->string('placeholder_doctor_path')->nullable()->after('favicon_path');
            $table->string('placeholder_facility_path')->nullable()->after('placeholder_doctor_path');
            $table->string('placeholder_pharmacy_path')->nullable()->after('placeholder_facility_path');
            $table->string('site_font_family')->default('geist')->after('copyright_name');
            $table->boolean('forum_rules_enabled')->default(true)->after('site_font_family');
            $table->text('forum_rules_title')->nullable()->after('forum_rules_enabled');
            $table->text('forum_rules_body')->nullable()->after('forum_rules_title');
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_published');
            $table->index('is_featured');
        });

        if (Schema::hasTable('facilities') && Schema::hasColumn('facilities', 'is_featured')) {
            DB::table('facilities')
                ->whereIn('slug', ['klinika-ana', 'univerzitetska-klinika-skopje', 'gradska-bolnica-bitola'])
                ->update(['is_featured' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropColumn('is_featured');
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'maintenance_message',
                'placeholder_doctor_path',
                'placeholder_facility_path',
                'placeholder_pharmacy_path',
                'site_font_family',
                'forum_rules_enabled',
                'forum_rules_title',
                'forum_rules_body',
            ]);
        });
    }
};
