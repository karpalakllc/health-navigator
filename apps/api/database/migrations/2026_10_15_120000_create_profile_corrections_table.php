<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public requests about a directory profile (docs/legal/research-memo.md
 * §2.1): „Пријави грешка во профилот“ (a wrong or outdated fact, answered
 * within 15 days) and the listed doctor's „Барање за приговор / отстранување“
 * (balancing test, reasoned answer within 30 days). Anyone may send one, so
 * user_id is optional. No IP address is stored: rate limits live in the cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->morphs('subject');
            $table->string('field', 32)->nullable();
            $table->text('message');
            $table->string('contact', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('open');
            // Fixed at receipt, so a later change of policy does not move it.
            $table->timestamp('due_at');
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('staff_alerted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_corrections');
    }
};
