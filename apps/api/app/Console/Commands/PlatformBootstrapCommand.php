<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PlatformUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

class PlatformBootstrapCommand extends Command
{
    protected $signature = 'platform:bootstrap {--seed-demo : Also run full local demo seeders}';

    protected $description = 'Migrate DB, seed site settings + RBAC, and ensure admin can access Filament';

    public function handle(): int
    {
        $this->info('Running migrations…');
        $this->call('migrate', ['--force' => true]);

        $this->info('Seeding site settings and permissions…');
        $this->call('db:seed', ['--class' => SiteSettingsSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => PlatformUserSeeder::class, '--force' => true]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminEmail = env('PLATFORM_ADMIN_EMAIL', 'admin@zdravje360.test');

        $admin = User::query()->where('email', $adminEmail)->first();

        if ($admin === null) {
            $this->error("No admin user at {$adminEmail}. Set PLATFORM_ADMIN_EMAIL / PLATFORM_ADMIN_PASSWORD in .env and re-run.");

            return self::FAILURE;
        }

        if (! $admin->hasRole('Administrator')) {
            $admin->syncRoles(['Administrator']);
        }

        if (! $admin->can('admin.access')) {
            $this->error('Admin user still lacks admin.access permission.');

            return self::FAILURE;
        }

        if ($this->option('seed-demo')) {
            $this->info('Seeding demo directory data…');
            $this->call('db:seed', ['--force' => true]);
        }

        $this->newLine();
        $this->components->info('Filament admin is ready.');
        $this->line("  URL:   <href=http://127.0.0.1:8000/admin>http://127.0.0.1:8000/admin</>");
        $this->line("  Email: {$adminEmail}");
        $this->line('  Pass:  (PLATFORM_ADMIN_PASSWORD from .env, default: password)');
        $this->newLine();
        $this->line('Start API: php artisan serve --port=8000');

        return self::SUCCESS;
    }
}
