<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The member account that manages a doctor profile („Мој профил“), assigned
 * by staff. One account per profile and, for now, one profile per account:
 * the unique index enforces both directions. Deleting the account (or its
 * anonymisation, which unlinks first) leaves the profile unmanaged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamp('owner_linked_at')->nullable();
            $table->foreignId('owner_linked_by_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_linked_by_id');
            $table->dropUnique(['owner_user_id']);
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn('owner_linked_at');
        });
    }
};
