<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsLocalDemoData;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RichDemoSeeder extends Seeder
{
    use SeedsLocalDemoData;

    public function run(): void
    {
        if (! $this->shouldRunLocalDemoSeeders()) {
            $this->command?->warn('RichDemoSeeder skipped. Use APP_ENV=local (or development), or SEED_LOCAL_DEMO=true, then php artisan db:seed');

            return;
        }

        /** @var array{doctors: array<string, array<string, mixed>>, facilities: array<string, array<string, mixed>>, extra_members: array<int, array<string, string>>} $data */
        $data = require database_path('seeders/data/rich-profiles.php');

        foreach ($data['doctors'] as $slug => $profile) {
            $doctor = Doctor::query()->where('slug', $slug)->first();

            if ($doctor === null) {
                continue;
            }

            $doctor->update($profile);
        }

        foreach ($data['facilities'] as $slug => $profile) {
            $facility = Facility::query()->where('slug', $slug)->first();

            if ($facility === null) {
                continue;
            }

            $facility->update($profile);
        }

        foreach ($data['extra_members'] as $member) {
            User::query()->updateOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'password' => Hash::make($member['password']),
                    'role' => UserRole::Member,
                ],
            );
        }

        $this->seedShowcaseReviews();
    }

    private function seedShowcaseReviews(): void
    {
        $authors = User::query()
            ->whereIn('email', [
                'member@zdravje360.test',
                'marija@zdravje360.test',
                'stefan@zdravje360.test',
            ])
            ->get()
            ->keyBy('email');

        $reviewSets = [
            'ana-petrovska' => [
                ['email' => 'member@zdravje360.test', 'rating' => 5, 'body' => 'Многу јасно објаснување и љубезен пристап. Целосно ја препорачувам.', 'days' => 12],
                ['email' => 'marija@zdravje360.test', 'rating' => 5, 'body' => 'Професионална и темелна. Контролните прегледи се секогаш навремени.', 'days' => 28],
                ['email' => 'stefan@zdravje360.test', 'rating' => 4, 'body' => 'Одличен доктор, но понекогаш има подолго чекање во ординација.', 'days' => 45],
            ],
            'marko-stojanov' => [
                ['email' => 'marija@zdravje360.test', 'rating' => 5, 'body' => 'Најдобриот педијатар за нашето дете. Секогаш спокоен и јасен.', 'days' => 8],
                ['email' => 'member@zdravje360.test', 'rating' => 5, 'body' => 'Секогаш ни дава практични совети за нега дома.', 'days' => 22],
            ],
            'elena-dimitrova' => [
                ['email' => 'stefan@zdravje360.test', 'rating' => 5, 'body' => 'Помогна ми по спортска повреда — рехабилитацијата беше одлична.', 'days' => 15],
                ['email' => 'member@zdravje360.test', 'rating' => 4, 'body' => 'Стручна и директна. Препорачувам за ортопедски проблеми.', 'days' => 40],
            ],
            'igor-nikolov' => [
                ['email' => 'marija@zdravje360.test', 'rating' => 5, 'body' => 'Кожниот проблем се подобри по неколку недели. Благодарам!', 'days' => 18],
            ],
            'bojan-trajkovski' => [
                ['email' => 'member@zdravje360.test', 'rating' => 5, 'body' => 'Топла препорака за кардиолошки преглед — многу внимателен кон деталите.', 'days' => 9],
                ['email' => 'stefan@zdravje360.test', 'rating' => 4, 'body' => 'Понекогаш долги прегледи, но резултатите се објаснети.', 'days' => 33],
            ],
            'mila-jovanovska' => [
                ['email' => 'marija@zdravje360.test', 'rating' => 5, 'body' => 'Нашето дете се чувства безбедно на преглед. Објаснувања за родителите без грижи.', 'days' => 14],
            ],
            'petar-georgiev' => [
                ['email' => 'stefan@zdravje360.test', 'rating' => 4, 'body' => 'Добра ортопедска консултација — добивме јасен план за рамен зглоб.', 'days' => 21],
            ],
            'viktorija-simeonova' => [
                ['email' => 'member@zdravje360.test', 'rating' => 5, 'body' => 'Многу дискретен и професионален пристап.', 'days' => 7],
            ],
            'dimitar-kostov' => [
                ['email' => 'marija@zdravje360.test', 'rating' => 5, 'body' => 'Конечно објаснување за псоријаза и нега дома. Ви благодарам.', 'days' => 11],
            ],
        ];

        foreach ($reviewSets as $doctorSlug => $reviews) {
            $doctor = Doctor::query()->where('slug', $doctorSlug)->first();

            if ($doctor === null) {
                continue;
            }

            foreach ($reviews as $review) {
                $author = $authors->get($review['email']);

                if ($author === null) {
                    continue;
                }

                Review::query()->updateOrCreate(
                    [
                        'user_id' => $author->id,
                        'reviewable_type' => Doctor::class,
                        'reviewable_id' => $doctor->id,
                    ],
                    [
                        'rating' => $review['rating'],
                        'body' => $review['body'],
                        'status' => ReviewStatus::Approved,
                        'published_at' => now()->subDays($review['days']),
                    ],
                );
            }
        }
    }
}
