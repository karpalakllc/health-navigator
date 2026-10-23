<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin switches for the „first reviews“ features (docs/reviews-growth.md).
 * All on by default: each one only invites a patient to write, it never
 * changes what is checked or published.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const COLUMNS = [
        'reviews_prompt_on_profile',
        'reviews_prompt_after_search',
        'reviews_share_link',
        'reviews_mobile_sticky_cta',
    ];

    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->boolean($column)->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
