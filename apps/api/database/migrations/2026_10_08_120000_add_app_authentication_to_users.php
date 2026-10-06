<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storage for the admin panel's authenticator-app second factor (Filament's
 * AppAuthentication). Both columns hold ciphertext: the model casts them
 * `encrypted` / `encrypted:array` under APP_KEY, and the recovery codes inside
 * that array are additionally bcrypt-hashed, so text — not a fixed-width string —
 * is the right type. Rotating APP_KEY without APP_PREVIOUS_KEYS makes every
 * enrolled secret unreadable (infra/deploy.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
