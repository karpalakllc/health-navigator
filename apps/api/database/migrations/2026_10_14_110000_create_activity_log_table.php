<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit log (spatie/laravel-activitylog): who changed a doctor profile,
 * decided a change request or doctor reply, resolved a report or suspended an
 * account, and when. No IP address or user agent is stored. Rows older than
 * 365 days are deleted by `activitylog:clean` (routes/console.php).
 *
 * The package's own migration stub, plus an index on created_at for the
 * daily prune and the newest-first viewer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
