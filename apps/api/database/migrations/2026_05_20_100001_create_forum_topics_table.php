<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('replies_count')->default(0);
            $table->timestamp('last_post_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('moderated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamps();

            $table->unique(['forum_category_id', 'slug']);
            $table->index(['forum_category_id', 'status', 'is_pinned', 'last_post_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_topics');
    }
};
