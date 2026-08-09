<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Specialty;
use App\Support\Slug;
use Database\Seeders\Concerns\SeedsLocalDemoData;
use Illuminate\Database\Seeder;

class DoctorDirectorySeeder extends Seeder
{
    use SeedsLocalDemoData;

    public function run(): void
    {
        if (! $this->shouldRunLocalDemoSeeders()) {
            $this->command?->warn('DoctorDirectorySeeder skipped. Use APP_ENV=local (or development), or set SEED_LOCAL_DEMO=true in .env, then php artisan db:seed');

            return;
        }

        $definitions = [
            ['name' => 'Кардиологија', 'slug' => 'kardiologija', 'sort_order' => 1, 'description' => 'Прегледи и долгорочна нега на срцеви и васкуларни заболувања; превенција на хипертензија и ишемија.'],
            ['name' => 'Педијатрија', 'slug' => 'pedijatrija', 'sort_order' => 2, 'description' => 'Здравје на деца и адолесценти: растење, вакцинации, чести инфекции, алергии.'],
            ['name' => 'Ортопедија', 'slug' => 'ortopedija', 'sort_order' => 3, 'description' => 'Коски, зглобови, спортски повреди и рехабилитација со ортопедски преглед.'],
            ['name' => 'Дерматологија', 'slug' => 'dermatologija', 'sort_order' => 4, 'description' => 'Кожа, коса, нокти, скрининг и третман на хронични дерматолошки состојби.'],
            ['name' => 'Гинекологија', 'slug' => 'ginekologija', 'sort_order' => 5, 'description' => 'Репродуктивно здравје, бременост, профилактика и минимално инвазивни процедури.'],
        ];

        $specialties = collect($definitions)->map(function (array $definition) {
            return Specialty::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'sort_order' => $definition['sort_order'],
                    'is_published' => true,
                ],
            );
        });

        $doctors = [
            [
                'full_name' => 'д-р Ана Петровска',
                'slug' => 'ana-petrovska',
                'title' => 'д-р',
                'city' => 'Скопје',
                'specialty_slugs' => ['kardiologija'],
                'primary' => 'kardiologija',
            ],
            [
                'full_name' => 'д-р Марко Стојанов',
                'slug' => 'marko-stojanov',
                'title' => 'д-р',
                'city' => 'Скопје',
                'specialty_slugs' => ['pedijatrija', 'kardiologija'],
                'primary' => 'pedijatrija',
            ],
            [
                'full_name' => 'д-р Елена Димитрова',
                'slug' => 'elena-dimitrova',
                'title' => 'д-р',
                'city' => 'Битола',
                'specialty_slugs' => ['ortopedija'],
                'primary' => 'ortopedija',
            ],
            [
                'full_name' => 'д-р Игор Николов',
                'slug' => 'igor-nikolov',
                'title' => 'д-р',
                'city' => 'Охрид',
                'specialty_slugs' => ['dermatologija'],
                'primary' => 'dermatologija',
            ],
            [
                'full_name' => 'д-р Софија Ристеска',
                'slug' => 'sofija-risteska',
                'title' => 'д-р',
                'city' => 'Прилеп',
                'specialty_slugs' => ['ginekologija'],
                'primary' => 'ginekologija',
            ],
            [
                'full_name' => 'д-р Бојан Трајковски',
                'slug' => 'bojan-trajkovski',
                'title' => 'д-р',
                'city' => 'Скопје',
                'specialty_slugs' => ['kardiologija'],
                'primary' => 'kardiologija',
            ],
            [
                'full_name' => 'д-р Мила Јовановска',
                'slug' => 'mila-jovanovska',
                'title' => 'д-р',
                'city' => 'Скопје',
                'specialty_slugs' => ['pedijatrija'],
                'primary' => 'pedijatrija',
            ],
            [
                'full_name' => 'д-р Петар Георгиев',
                'slug' => 'petar-georgiev',
                'title' => 'д-р',
                'city' => 'Битола',
                'specialty_slugs' => ['ortopedija', 'dermatologija'],
                'primary' => 'ortopedija',
            ],
            [
                'full_name' => 'д-р Викторија Симеонова',
                'slug' => 'viktorija-simeonova',
                'title' => 'д-р',
                'city' => 'Скопје',
                'specialty_slugs' => ['ginekologija', 'pedijatrija'],
                'primary' => 'ginekologija',
            ],
            [
                'full_name' => 'д-р Димитар Костов',
                'slug' => 'dimitar-kostov',
                'title' => 'д-р',
                'city' => 'Охрид',
                'specialty_slugs' => ['dermatologija'],
                'primary' => 'dermatologija',
            ],
        ];

        foreach ($doctors as $definition) {
            $doctor = Doctor::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'full_name' => $definition['full_name'],
                    'title' => $definition['title'],
                    'bio' => 'Краток биографски текст — ќе се замени со побогат профил од демо сидот за објавени лекари.',
                    'city' => $definition['city'],
                    'phone' => '+389 70 000 000',
                    'email' => $definition['slug'].'@example.test',
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );

            $sync = [];

            foreach ($definition['specialty_slugs'] as $specialtySlug) {
                $specialty = $specialties->firstWhere('slug', $specialtySlug);

                if ($specialty) {
                    $sync[$specialty->id] = [
                        'is_primary' => $specialtySlug === $definition['primary'],
                    ];
                }
            }

            $doctor->specialties()->sync($sync);
        }

        Doctor::factory()
            ->count(2)
            ->unpublished()
            ->create()
            ->each(function (Doctor $doctor) use ($specialties): void {
                $specialty = $specialties->random();
                $doctor->specialties()->sync([
                    $specialty->id => ['is_primary' => true],
                ]);
            });

        Specialty::factory()->unpublished()->create([
            'name' => 'Необјавена специјалност',
            'slug' => Slug::fromName('neobjavena-specijalnost'),
        ]);
    }
}
