<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The reply under a review can now come from staff (entered on the profile's
 * behalf, as before) or from the linked doctor themselves. A doctor's reply
 * may wait for staff approval: only an approved one is public.
 *
 * Existing responses were all staff-entered and public, so they are
 * backfilled as `staff` / `approved`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('response_source', 16)->nullable();
            $table->string('response_status', 16)->nullable();
            $table->foreignId('response_moderated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('response_moderated_at')->nullable();
            $table->text('response_rejection_note')->nullable();

            $table->index(['response_source', 'response_status']);
        });

        DB::table('reviews')
            ->whereNotNull('response_body')
            ->whereNull('response_source')
            ->update(['response_source' => 'staff', 'response_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['response_source', 'response_status']);
            $table->dropConstrainedForeignId('response_moderated_by_id');
            $table->dropColumn(['response_source', 'response_status', 'response_moderated_at', 'response_rejection_note']);
        });
    }
};
