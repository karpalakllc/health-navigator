<?php

namespace App\Console\Commands;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PlatformUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\PermissionRegistrar;

class PlatformBootstrapCommand extends Command
{
    protected $signature = 'platform:bootstrap
        {--seed-demo : Also run full local demo seeders}
        {--promote-existing : Promote an existing non-staff account at the admin email to Administrator}';

    /**
     * The address PlatformUserSeeder and .env.example ship with. Anyone can
     * register it, so it is only acceptable where the seeder itself runs.
     */
    private const DEFAULT_ADMIN_EMAIL = 'admin@zdravje360.test';

    protected $description = 'Migrate DB, seed site settings + RBAC, and ensure admin can access Filament';

    public function handle(): int
    {
        $adminEmail = (string) config('zdravje.admin.email');
        $adminPassword = config('zdravje.admin.password');

        if (strcasecmp($adminEmail, self::DEFAULT_ADMIN_EMAIL) === 0 && ! app()->environment(['local', 'testing'])) {
            $this->error('PLATFORM_ADMIN_EMAIL is still the default '.self::DEFAULT_ADMIN_EMAIL.'.');
            $this->line('Set it to an address you control before bootstrapping a '.app()->environment().' environment.');

            return self::FAILURE;
        }

        $this->info('Running migrations…');
        $this->call('migrate', ['--force' => true]);

        $this->info('Seeding site settings and permissions…');
        $this->call('db:seed', ['--class' => SiteSettingsSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => PlatformUserSeeder::class, '--force' => true]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

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

            // The same rule registration and reset use; this is the most
            // privileged credential on the platform.
            $validator = Validator::make(
                ['password' => $adminPassword],
                ['password' => ['required', 'string', Password::defaults()]],
            );

            if ($validator->fails()) {
                $this->error('PLATFORM_ADMIN_PASSWORD is too weak:');

                foreach ($validator->errors()->all() as $message) {
                    $this->line("  - {$message}");
                }

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
        } elseif ($admin->user_kind !== UserKind::Staff) {
            // Anyone can self-register this address (verified or not) before the
            // first deploy. Promoting it silently would hand Administrator, with
            // the registrant's own password, to whoever got there first.
            if (! $this->option('promote-existing')) {
                $this->error("{$adminEmail} belongs to an existing non-staff account; refusing to promote it to Administrator.");
                $this->line('If you own that account, re-run with --promote-existing. Otherwise set PLATFORM_ADMIN_EMAIL to another address.');

                return self::FAILURE;
            }

            $this->warn("Promoting existing account {$adminEmail}; it keeps its current password.");
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
