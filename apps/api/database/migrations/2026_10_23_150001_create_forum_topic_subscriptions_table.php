<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Следи ја темата“: who is told about new published replies in a topic.
 * Deleted with the account or the topic; anonymisation deletes them too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_topic_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('forum_topic_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'forum_topic_id']);
            $table->index('forum_topic_id');
        });

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('forum_reply')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('forum_reply');
        });

        Schema::dropIfExists('forum_topic_subscriptions');
    }
};
