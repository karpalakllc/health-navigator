<?php

namespace Database\Seeders;

use App\Enums\UserKind;
use App\Models\User;
use App\Support\EmailAddress;
use App\Support\RoleCatalog;
use Database\Seeders\Concerns\SeedsLocalDemoData;
use Database\Seeders\Concerns\SeedsUsernames;
use Illuminate\Database\Seeder;

class PlatformUserSeeder extends Seeder
{
    use SeedsLocalDemoData, SeedsUsernames;

    /**
     * Each lookup normalises the configured address the way the User model
     * stores it. updateOrCreate() matching the raw value missed the lowercased
     * row on the second run and then hit the unique index inserting it again.
     */
    public function run(): void
    {
        if (! $this->shouldRunLocalDemoSeeders()) {
            $this->command?->warn('PlatformUserSeeder skipped. Use APP_ENV=local (or development), or SEED_LOCAL_DEMO=true, then php artisan db:seed');

            return;
        }

        $admin = User::query()->updateOrCreate(
            ['email' => EmailAddress::normalize((string) config('zdravje.admin.email'))],
            [
                'name' => 'Platform Admin',
                'username' => 'zdravje_admin',
                'password' => config('zdravje.admin.password') ?? 'password',
                'user_kind' => UserKind::Staff,
                'email_verified_at' => now(),
            ],
        );

        $moderator = User::query()->updateOrCreate(
            ['email' => EmailAddress::normalize((string) config('zdravje.seed.moderator.email'))],
            [
                'name' => 'Platform Moderator',
                'username' => 'zdravje_moderator',
                'password' => config('zdravje.seed.moderator.password'),
                'user_kind' => UserKind::Staff,
                'email_verified_at' => now(),
            ],
        );

        $member = User::query()->updateOrCreate(
            ['email' => EmailAddress::normalize((string) config('zdravje.seed.member.email'))],
            [
                'name' => 'Test Member',
                'username' => self::seededUsername((string) config('zdravje.seed.member.email')),
                'password' => config('zdravje.seed.member.password'),
                'user_kind' => UserKind::Client,
                'email_verified_at' => now(),
            ],
        );

        // Only to an account holding no role yet, so a reseed never undoes a
        // promotion or demotion made in the panel.
        $this->ensureRole($admin, RoleCatalog::ADMINISTRATOR);
        $this->ensureRole($moderator, RoleCatalog::MODERATOR);
        $this->ensureRole($member, RoleCatalog::MEMBER);
    }

    private function ensureRole(User $user, string $role): void
    {
        if (! $user->roles()->exists()) {
            $user->assignRole(RoleCatalog::ensure($role));
        }
    }
}
