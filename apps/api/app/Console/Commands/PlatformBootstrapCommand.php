<?php

namespace App\Console\Commands;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\DeploymentEnvironment;
use App\Support\EmailAddress;
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
        // Normalised as the User model stores it. Looking up the raw value missed
        // the row the model had lowercased, so a mixed-case PLATFORM_ADMIN_EMAIL
        // tried to create the admin again on every later deploy.
        $adminEmail = EmailAddress::normalize((string) config('zdravje.admin.email'));
        $adminPassword = config('zdravje.admin.password');

        if ($adminEmail === self::DEFAULT_ADMIN_EMAIL && DeploymentEnvironment::isDeployed()) {
            $this->error('PLATFORM_ADMIN_EMAIL is still the default '.self::DEFAULT_ADMIN_EMAIL.'.');
            $this->line('Set it to an address you control before bootstrapping a '.app()->environment().' environment.');

            return self::FAILURE;
        }

        $this->info('Running migrations…');
        $this->call('migrate', ['--force' => true]);

        $this->info('Seeding site settings and permissions…');
        $this->call('db:seed', ['--class' => SiteSettingsSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::query()->where('email', $adminEmail)->first();

        // The admin is created here, never by PlatformUserSeeder: that seeder is demo
        // data (it upserts by email with a fallback password), and running it first
        // would bypass both the password rule and the refusal to promote a
        // self-registered account below. Demo moderator/member accounts come from
        // `--seed-demo` / `db:seed`.
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

            // Local and development keep the documented throwaway credential
            // (.env.example); everywhere else, including the test suite, the rule holds.
            if ($validator->fails() && ! app()->environment(['local', 'development'])) {
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

            // The flag vouches for the account's owner, not for its password. An
            // unverified (or contested) account's password is whichever sign-up
            // got there first, and promoting would verify it and make that
            // password the Administrator's. Proof of the mailbox comes first.
            if (! $admin->hasVerifiedEmail() || $admin->registration_contested_at !== null) {
                $this->error("{$adminEmail} is not verified (or its sign-up is contested); refusing to promote it, even with --promote-existing.");
                $this->line('Confirm the address first: open the verification link sent to it, or complete a password reset from that mailbox. Then re-run.');

                return self::FAILURE;
            }

            $this->warn("Promoting existing account {$adminEmail}; it keeps its current password.");
            $admin->update(['user_kind' => UserKind::Staff]);
        }

        // Staff are verified from creation; an unverified one is what the public
        // sign-up treats as "pending". Only an existing staff account can reach
        // this unverified — a promoted client was refused above unless verified.
        if (! $admin->hasVerifiedEmail()) {
            $admin->markEmailAsVerified();
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
