<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('registrations_enabled')->default(true);
            $table->boolean('require_email_verification')->default(false);
            $table->boolean('maintenance_mode')->default(false);
            $table->boolean('public_guidance')->default(false);
            $table->boolean('public_products')->default(false);
            $table->boolean('public_pharmacies')->default(false);
            $table->boolean('public_forum')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
