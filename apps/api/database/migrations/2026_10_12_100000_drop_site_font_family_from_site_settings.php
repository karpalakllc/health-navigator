<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin "Site font" setting is gone: the web app's type system is fixed
 * (Geologica + Source Sans 3) and never read it after the D2a redesign.
 * down() restores the column with its original default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('site_font_family');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('site_font_family')->default('geist')->after('copyright_name');
        });
    }
};
