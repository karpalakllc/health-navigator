<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W8-B member notifications.
 *
 * notification_preferences: one row per member who changed a setting (no row
 * = the defaults in App\Enums\NotificationType). email_enabled is the global
 * e-mail switch; each type has its own. The monthly impact digest is opt-in
 * (off); digest_invited_at records the one-time in-app invitation.
 *
 * member_notifications: the in-app list („Известувања“). `data` holds only
 * what the line shows (the profile's public name and path, a count) — never
 * another member's name. Pruned 180 days after creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('moderation')->default(true);
            $table->boolean('review_reply')->default(true);
            $table->boolean('review_helpful')->default(true);
            $table->boolean('impact_digest')->default(false);
            $table->timestamp('digest_invited_at')->nullable();
            $table->timestamps();
        });

        Schema::create('member_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_notifications');
        Schema::dropIfExists('notification_preferences');
    }
};
