<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->boolean('is_sponsored')->default(false)->after('is_featured');
            $table->index('is_sponsored');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropIndex(['is_sponsored']);
            $table->dropColumn('is_sponsored');
        });
    }
};
