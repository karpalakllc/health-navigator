<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin switches for the forum upgrades (docs/forum.md): following a topic,
 * „Најкорисен одговор“ and profile cards for pasted links. On by default;
 * none of them changes what is moderated or published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('forum_follow_enabled')->default(true);
            $table->boolean('forum_accepted_answers_enabled')->default(true);
            $table->boolean('forum_profile_cards_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'forum_follow_enabled',
                'forum_accepted_answers_enabled',
                'forum_profile_cards_enabled',
            ]);
        });
    }
};
