<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_kind', 16)->default('client')->after('role');
        });

        DB::table('users')->whereIn('role', ['admin', 'moderator'])->update(['user_kind' => 'staff']);
        DB::table('users')->where('role', 'member')->update(['user_kind' => 'client']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('user_kind');
        });
    }
};
