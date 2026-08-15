<?php

namespace App\Console\Commands;

use App\Enums\UserKind;
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

        $adminEmail = (string) config('zdravje.admin.email');
        $adminPassword = config('zdravje.admin.password');

        $admin = User::query()->where('email', $adminEmail)->first();

        // PlatformUserSeeder deliberately self-skips outside local/testing, so on a
        // fresh staging or production database nothing above created an admin. Create
        // it here instead of failing — this command is the documented first-deploy step.
        if ($admin === null) {
            if (blank($adminPassword)) {
                $this->error("No admin user at {$adminEmail}, and PLATFORM_ADMIN_PASSWORD is not set.");
                $this->line('Set PLATFORM_ADMIN_EMAIL and PLATFORM_ADMIN_PASSWORD in the environment, then re-run.');

                return self::FAILURE;
            }

            $admin = User::query()->create([
                'name' => 'Platform Admin',
                'email' => $adminEmail,
                'password' => $adminPassword,
                'role' => UserRole::Admin,
                'user_kind' => UserKind::Staff,
                'email_verified_at' => now(),
            ]);

            $this->components->info("Created admin user {$adminEmail}.");
        }

        if ($admin->user_kind !== UserKind::Staff) {
            $admin->update(['user_kind' => UserKind::Staff]);
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
        $this->line('  URL:   <href=http://127.0.0.1:8000/admin>http://127.0.0.1:8000/admin</>');
        $this->line("  Email: {$adminEmail}");
        $this->line('  Pass:  PLATFORM_ADMIN_PASSWORD from the environment');
        $this->newLine();
        $this->line('Start API: php artisan serve --port=8000');

        return self::SUCCESS;
    }
}
