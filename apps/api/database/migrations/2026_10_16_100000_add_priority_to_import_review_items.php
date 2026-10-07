<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import review items get a priority, so the queue shows the cases that
 * matter most first (published profiles, many doctors behind one decision).
 * The verification engine sets it (docs/verification.md); 0 = ordinary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_review_items', function (Blueprint $table) {
            $table->unsignedInteger('priority')->default(0);
            $table->index(['status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('import_review_items', function (Blueprint $table) {
            $table->dropIndex(['status', 'priority']);
            $table->dropColumn('priority');
        });
    }
};
