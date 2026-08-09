<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `require_email_verification` was a toggle that enforced nothing.
 *
 * Turning it on left email_verified_at null and changed no other behaviour: no
 * verification mail was ever sent, no route used the `verified` middleware, and
 * registration still issued a full API token. It was also advertised on the
 * public settings endpoint, so it actively told operators they had a gate they
 * did not have.
 *
 * Removed rather than half-implemented. Real verification is a registration
 * contract change (201-with-token becomes 202-no-token) and belongs on the
 * roadmap, not behind a switch that lies.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('site_settings', 'require_email_verification')) {
            Schema::table('site_settings', function (Blueprint $table) {
                $table->dropColumn('require_email_verification');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('site_settings', 'require_email_verification')) {
            Schema::table('site_settings', function (Blueprint $table) {
                $table->boolean('require_email_verification')->default(false);
            });
        }
    }
};
