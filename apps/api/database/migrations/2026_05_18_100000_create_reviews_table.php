<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->morphs('reviewable');
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('moderated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'reviewable_type', 'reviewable_id']);
            $table->index(['reviewable_type', 'reviewable_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
