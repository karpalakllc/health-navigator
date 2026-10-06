<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * „Истакнат“ (unpaid, editorial) and „Спонзорирано“ (paid) are exclusive from
 * now on (Doctor::booted, DoctorForm). A doctor flagged both keeps the paid
 * label, which must always be disclosed, and loses the featured one, which
 * must never be bought. Not reversible: down() cannot know which rows it
 * changed, and putting both back would restore the contradiction.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cleared = DB::table('doctors')
            ->where('is_featured', true)
            ->where('is_sponsored', true)
            ->update(['is_featured' => false, 'updated_at' => now()]);

        if ($cleared > 0) {
            Log::notice("Cleared „featured“ on {$cleared} sponsored doctor(s): the two labels are now exclusive.");
            echo "  Cleared featured on {$cleared} sponsored doctor(s).\n";
        }
    }

    public function down(): void
    {
        //
    }
};
