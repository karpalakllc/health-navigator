<?php

namespace Database\Seeders;

use App\Enums\UserKind;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\EmailAddress;
use Database\Seeders\Concerns\SeedsLocalDemoData;
use Illuminate\Database\Seeder;

class PlatformUserSeeder extends Seeder
{
    use SeedsLocalDemoData;

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

        User::query()->updateOrCreate(
            ['email' => EmailAddress::normalize((string) config('zdravje.admin.email'))],
            [
                'name' => 'Platform Admin',
                'password' => config('zdravje.admin.password') ?? 'password',
                'role' => UserRole::Admin,
                'user_kind' => UserKind::Staff,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => EmailAddress::normalize((string) config('zdravje.seed.moderator.email'))],
            [
                'name' => 'Platform Moderator',
                'password' => config('zdravje.seed.moderator.password'),
                'role' => UserRole::Moderator,
                'user_kind' => UserKind::Staff,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => EmailAddress::normalize((string) config('zdravje.seed.member.email'))],
            [
                'name' => 'Test Member',
                'password' => config('zdravje.seed.member.password'),
                'role' => UserRole::Member,
                'user_kind' => UserKind::Client,
                'email_verified_at' => now(),
            ],
        );
    }
}
