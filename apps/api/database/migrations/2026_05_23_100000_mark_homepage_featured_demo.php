<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demo content, not schema. Guarded so it cannot promote a real clinic that
     * happens to share one of these slugs (e.g. univerzitetska-klinika-skopje).
     */
    private function demoEnvironment(): bool
    {
        return app()->environment(['local', 'testing', 'development']);
    }

    public function up(): void
    {
        if (! $this->demoEnvironment()) {
            return;
        }

        if (Schema::hasColumn('doctors', 'is_featured')) {
            DB::table('doctors')
                ->whereIn('slug', [
                    'ana-petrovska',
                    'marko-stojanov',
                    'elena-dimitrova',
                    'igor-nikolov',
                ])
                ->update(['is_featured' => true]);
        }

        if (Schema::hasColumn('facilities', 'is_featured')) {
            DB::table('facilities')
                ->whereIn('slug', [
                    'klinika-ana',
                    'univerzitetska-klinika-skopje',
                    'gradska-bolnica-bitola',
                ])
                ->update(['is_featured' => true]);
        }
    }

    public function down(): void
    {
        if (! $this->demoEnvironment()) {
            return;
        }

        if (Schema::hasColumn('doctors', 'is_featured')) {
            DB::table('doctors')
                ->whereIn('slug', [
                    'ana-petrovska',
                    'marko-stojanov',
                    'elena-dimitrova',
                    'igor-nikolov',
                ])
                ->update(['is_featured' => false]);
        }

        if (Schema::hasColumn('facilities', 'is_featured')) {
            DB::table('facilities')
                ->whereIn('slug', [
                    'klinika-ana',
                    'univerzitetska-klinika-skopje',
                    'gradska-bolnica-bitola',
                ])
                ->update(['is_featured' => false]);
        }
    }
};
