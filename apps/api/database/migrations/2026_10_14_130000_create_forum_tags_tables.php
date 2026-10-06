<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keywords („клучни зборови“) on forum topics, used for <title>/description,
 * tag pages (/forum/tags/{slug}) and „Слични теми“ (docs/seo.md).
 *
 * forum_tags.match_key folds Cyrillic, Latin and diacritic spellings of the
 * same words onto one key (ForumTagNormalizer), so "проширени вени",
 * "prosireni veni" and "proshireni veni" are one tag. `latin` is the
 * diacritic-free Latin spelling shown to Latin-script searchers.
 *
 * forum_tag_topic.confirmed: false for a member's suggestion, true once staff
 * or a moderator saved the topic's keywords in the admin panel. Doctor
 * profiles only link topics through confirmed tags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->string('match_key', 80)->unique();
            $table->string('latin', 80);
            $table->timestamps();
        });

        Schema::create('forum_tag_topic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_tag_id')->constrained('forum_tags')->cascadeOnDelete();
            $table->foreignId('forum_topic_id')->constrained('forum_topics')->cascadeOnDelete();
            $table->boolean('confirmed')->default(false);
            $table->timestamps();

            $table->unique(['forum_tag_id', 'forum_topic_id']);
            $table->index('forum_topic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_tag_topic');
        Schema::dropIfExists('forum_tags');
    }
};
