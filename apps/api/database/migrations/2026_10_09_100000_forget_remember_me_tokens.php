<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The admin login offered "remember me", whose cookie signed its holder back in
 * for 400 days without the password or the second factor. The checkbox is gone
 * and IgnoreRememberMeCookie refuses such cookies; clearing every remember
 * token as well means a cookie already out there matches nothing, even if that
 * middleware is ever taken off a route.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);
    }

    /**
     * Nothing to restore: the old tokens are gone, which is the point.
     */
    public function down(): void
    {
        //
    }
};
