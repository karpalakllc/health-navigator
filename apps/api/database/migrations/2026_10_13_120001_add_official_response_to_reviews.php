<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Right of reply: staff attach an official response from the reviewed doctor
 * or facility to a review. At most one per review, so it lives on the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('response_body')->nullable();
            $table->foreignId('response_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('response_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('response_by_id');
            $table->dropColumn(['response_body', 'response_at']);
        });
    }
};
