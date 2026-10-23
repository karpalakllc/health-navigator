<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision 2026-10-23: no standing disclaimer paragraph in the footer
 * (the /disclaimer page stays linked). The setting stays, empty by default; a
 * row that still holds the old untouched default is cleared, while any text
 * the admin wrote is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings') || ! Schema::hasColumn('site_settings', 'footer_disclaimer_text')) {
            return;
        }

        DB::table('site_settings')
            ->where('footer_disclaimer_text', SiteSetting::LEGACY_FOOTER_DISCLAIMER)
            ->update(['footer_disclaimer_text' => null]);

        Cache::forget(SiteSetting::CACHE_KEY);
    }

    /** Not reversed: an empty paragraph is the intended state. */
    public function down(): void {}
};
