<?php

namespace Database\Seeders;

use App\Enums\ReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\Concerns\SeedsLocalDemoData;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    use SeedsLocalDemoData;

    public function run(): void
    {
        if (! $this->shouldRunLocalDemoSeeders()) {
            $this->command?->warn('ReviewSeeder skipped. Use APP_ENV=local (or development), or SEED_LOCAL_DEMO=true, then php artisan db:seed');

            return;
        }

        $member = User::query()->where('email', 'member@zdravje360.test')->first();

        if ($member === null) {
            $this->command?->warn('ReviewSeeder skipped: member user not found.');

            return;
        }

        $doctor = Doctor::query()->where('slug', 'ana-petrovska')->first();
        $facility = Facility::query()->where('slug', 'klinika-ana')->first();

        if ($doctor) {
            Review::query()->updateOrCreate(
                [
                    'user_id' => $member->id,
                    'reviewable_type' => Doctor::class,
                    'reviewable_id' => $doctor->id,
                ],
                [
                    'rating' => 5,
                    'body' => 'Одлично искуство — јасни објаснувања, навремен термин и почитува кон прашањата. Препорачувам за кардиолошки контроли.',
                    'status' => ReviewStatus::Approved,
                    'published_at' => now()->subDays(2),
                ],
            );

            $marko = Doctor::query()->where('slug', 'marko-stojanov')->first();

            if ($marko) {
                Review::query()->updateOrCreate(
                    [
                        'user_id' => $member->id,
                        'reviewable_type' => Doctor::class,
                        'reviewable_id' => $marko->id,
                    ],
                    [
                        'rating' => 4,
                        'body' => 'Сеуште чекам одобрување на мојата рецензија.',
                        'status' => ReviewStatus::Pending,
                        'published_at' => null,
                    ],
                );
            }
        }

        if ($facility) {
            Review::query()->updateOrCreate(
                [
                    'user_id' => $member->id,
                    'reviewable_type' => Facility::class,
                    'reviewable_id' => $facility->id,
                ],
                [
                    'rating' => 3,
                    'body' => 'Чекањето во холот беше подолго од очекуваното, но персоналот беше љубезен. Чисто и средено.',
                    'status' => ReviewStatus::Approved,
                    'published_at' => now()->subDay(),
                ],
            );
        }

        $otherMember = User::factory()->create([
            'name' => 'Another Member',
            'email' => 'member2@zdravje360.test',
            'password' => 'password',
        ]);

        if ($doctor) {
            Review::query()->updateOrCreate(
                [
                    'user_id' => $otherMember->id,
                    'reviewable_type' => Doctor::class,
                    'reviewable_id' => $doctor->id,
                ],
                [
                    'rating' => 2,
                    'body' => 'Не би препорачал по ова искуство.',
                    'status' => ReviewStatus::Rejected,
                    'rejection_note' => 'Example rejected review for local dev.',
                    'published_at' => null,
                ],
            );
        }
    }
}
