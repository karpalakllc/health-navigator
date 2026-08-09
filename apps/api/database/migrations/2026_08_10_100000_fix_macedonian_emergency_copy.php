<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The emergency-call instruction was written with Bulgarian "повикайте" (U+0439),
 * a letter absent from the Macedonian alphabet; the correct form is "повикајте"
 * (U+0458). Fixing the PHP constant is not enough: SiteSetting::current() persists
 * the default into site_settings on first boot, and publicBranding() serves the
 * stored column — so every already-seeded environment keeps emitting the old text.
 *
 * Deliberately a targeted REPLACE rather than an overwrite to the new default, so
 * that copy an administrator has edited through Filament is left alone.
 */
return new class extends Migration
{
    private const WRONG = 'повикайте';

    private const RIGHT = 'повикајте';

    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        foreach (['footer_emergency_text', 'footer_disclaimer_text', 'maintenance_message'] as $column) {
            if (! Schema::hasColumn('site_settings', $column)) {
                continue;
            }

            DB::table('site_settings')
                ->where($column, 'like', '%'.self::WRONG.'%')
                ->update([
                    $column => DB::raw(
                        'replace('.$column.", '".self::WRONG."', '".self::RIGHT."')"
                    ),
                ]);
        }
    }

    /**
     * Intentionally not reversible: this repairs a spelling error in
     * safety-critical copy. Re-introducing it has no legitimate use.
     */
    public function down(): void
    {
        //
    }
};
