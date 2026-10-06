<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Ова е мој профил“: a signed-in member says a doctor profile is theirs.
 * Staff verify the person outside the platform and then assign the account
 * (doctors.owner_user_id) or reject the request. No documents are uploaded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_claim_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('contact', 255);
            $table->string('status', 16)->default('pending');
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'doctor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_claim_requests');
    }
};
