<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Потсети ме за 2 недели“ (W8-B): a member asked, on a profile, to be
 * e-mailed once about reviewing it. Only who, which profile and when: the
 * row is deleted when the one e-mail goes (or is no longer needed), when the
 * member cancels it, and with the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reviewable_type');
            $table->unsignedBigInteger('reviewable_id');
            $table->timestamp('remind_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'reviewable_type', 'reviewable_id']);
            $table->index('remind_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reminders');
    }
};
