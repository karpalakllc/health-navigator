<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service account deletion anonymises in place (D5, App\Actions\AnonymiseUser):
 * reviews and forum content reference users with restrictOnDelete and stay
 * public, so the row is kept with its personal data cleared and this set.
 * Public surfaces then show the author as a deleted user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymised_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anonymised_at');
        });
    }
};
