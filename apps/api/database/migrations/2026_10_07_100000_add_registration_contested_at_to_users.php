<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Set when a second sign-up arrives for an address whose client account is
 * still unverified. Verifying a contested account confirms the address but does
 * not activate the stored password; the mailbox owner chooses one through a
 * reset link instead (AuthController::verifyEmail()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('registration_contested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('registration_contested_at');
        });
    }
};
