<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsLocalDemoData;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlatformUserSeeder extends Seeder
{
    use SeedsLocalDemoData;

    public function run(): void
    {
        if (! $this->shouldRunLocalDemoSeeders()) {
            $this->command?->warn('PlatformUserSeeder skipped. Use APP_ENV=local (or development), or SEED_LOCAL_DEMO=true, then php artisan db:seed');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => env('PLATFORM_ADMIN_EMAIL', 'admin@zdravje360.test')],
            [
                'name' => 'Platform Admin',
                'password' => env('PLATFORM_ADMIN_PASSWORD', 'password'),
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => env('PLATFORM_MODERATOR_EMAIL', 'moderator@zdravje360.test')],
            [
                'name' => 'Platform Moderator',
                'password' => env('PLATFORM_MODERATOR_PASSWORD', 'password'),
                'role' => UserRole::Moderator,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => env('PLATFORM_MEMBER_EMAIL', 'member@zdravje360.test')],
            [
                'name' => 'Test Member',
                'password' => env('PLATFORM_MEMBER_PASSWORD', 'password'),
                'role' => UserRole::Member,
                'email_verified_at' => now(),
            ],
        );
    }
}
