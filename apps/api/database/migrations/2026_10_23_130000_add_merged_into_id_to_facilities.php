<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A website-only facility found to duplicate a register facility is merged
 * into it (App\Support\Import\Facilities\FacilityMerger): soft-deleted and
 * pointed at the facility that stayed, so the merge can be read back, an
 * import that still meets the old record follows it, and repeating the
 * merge changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->foreignId('merged_into_id')->nullable()->after('import_source')->constrained('facilities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
        });
    }
};
