<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision 2026-10-23: no standing 194/112 sentence in the footer. The
 * setting stays (empty by default); a row that still holds the old untouched
 * default is cleared, while any text the admin wrote is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings') || ! Schema::hasColumn('site_settings', 'footer_emergency_text')) {
            return;
        }

        DB::table('site_settings')
            ->where('footer_emergency_text', SiteSetting::LEGACY_FOOTER_EMERGENCY)
            ->update(['footer_emergency_text' => null]);

        Cache::forget(SiteSetting::CACHE_KEY);
    }

    /**
     * Not reversed: an empty line is the intended state, and restoring the old
     * sentence would overrule the owner's decision.
     */
    public function down(): void {}
};
