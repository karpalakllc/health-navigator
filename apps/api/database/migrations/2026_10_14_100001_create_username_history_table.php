<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usernames given up by a rename, a staff rename or an account deletion,
 * held for six months so nobody else can take them over (App\Models\UsernameHistory).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('username_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username', 30);
            $table->string('username_normalized', 100)->index();
            $table->string('username_skeleton', 100)->index();
            $table->string('reason', 16);
            $table->string('note', 500)->nullable();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reserved_until')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('username_history');
    }
};
