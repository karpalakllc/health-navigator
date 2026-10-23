<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Најкорисен одговор“: the reply the topic's author (or a moderator) marked.
 * No foreign key (forum_posts already points at forum_topics); a reply that
 * is no longer published is simply not shown as the answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->unsignedBigInteger('accepted_post_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->dropColumn('accepted_post_id');
        });
    }
};
