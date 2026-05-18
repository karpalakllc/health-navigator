<?php

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('forum_topics_require_moderation')->default(true)->after('forum_rules_body');
            $table->boolean('forum_posts_require_moderation')->default(true)->after('forum_topics_require_moderation');
        });

        User::query()
            ->where('role', UserRole::Admin)
            ->where('user_kind', UserKind::Client)
            ->update(['user_kind' => UserKind::Staff]);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'forum_topics_require_moderation',
                'forum_posts_require_moderation',
            ]);
        });
    }
};
