<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the author ticked the community-rules consent while creating the topic
 * (ForumController::storeTopic). Null for topics that predate this column and
 * for topics created by staff in the admin panel or by seeders/factories,
 * which never pass through that consent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->timestamp('community_rules_accepted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->dropColumn('community_rules_accepted_at');
        });
    }
};
