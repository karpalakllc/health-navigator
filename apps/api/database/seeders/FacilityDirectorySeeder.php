<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsLocalDemoData;
use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use Illuminate\Database\Seeder;

class FacilityDirectorySeeder extends Seeder
{
    use SeedsLocalDemoData;

    public function run(): void
    {
        if (! $this->shouldRunLocalDemoSeeders()) {
            $this->command?->warn('FacilityDirectorySeeder skipped. Use APP_ENV=local (or development), or SEED_LOCAL_DEMO=true, then php artisan db:seed');

            return;
        }

        $definitions = [
            [
                'name' => 'Клиника Ана',
                'slug' => 'klinika-ana',
                'type' => FacilityType::Clinic,
                'city' => 'Скопје',
                'address' => 'ул. Пример 1',
                'doctor_slugs' => ['ana-petrovska', 'marko-stojanov'],
                'primary' => 'ana-petrovska',
            ],
            [
                'name' => 'Градска болница Битола',
                'slug' => 'gradska-bolnica-bitola',
                'type' => FacilityType::Hospital,
                'city' => 'Битола',
                'address' => 'ул. Пример 2',
                'doctor_slugs' => ['elena-dimitrova', 'petar-georgiev'],
                'primary' => 'elena-dimitrova',
            ],
            [
                'name' => 'Лабораторија Охрид',
                'slug' => 'laboratorija-ohrid',
                'type' => FacilityType::Laboratory,
                'city' => 'Охрид',
                'address' => 'ул. Пример 3',
                'doctor_slugs' => ['igor-nikolov', 'dimitar-kostov'],
                'primary' => 'igor-nikolov',
            ],
            [
                'name' => 'Клиника Софија',
                'slug' => 'klinika-sofija',
                'type' => FacilityType::Clinic,
                'city' => 'Прилеп',
                'address' => 'ул. Пример 4',
                'doctor_slugs' => ['sofija-risteska'],
                'primary' => 'sofija-risteska',
            ],
            [
                'name' => 'Универзитетска клиника Скопје',
                'slug' => 'univerzitetska-klinika-skopje',
                'type' => FacilityType::Hospital,
                'city' => 'Скопје',
                'address' => 'ул. Пример 5',
                'doctor_slugs' => ['bojan-trajkovski', 'mila-jovanovska', 'viktorija-simeonova'],
                'primary' => 'bojan-trajkovski',
            ],
            [
                'name' => 'Лабораторија Скопје',
                'slug' => 'laboratorija-skopje',
                'type' => FacilityType::Laboratory,
                'city' => 'Скопје',
                'address' => 'ул. Пример 6',
                'doctor_slugs' => ['marko-stojanov'],
                'primary' => 'marko-stojanov',
            ],
        ];

        foreach ($definitions as $definition) {
            $facility = Facility::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'description' => 'Објект за локален развој — побогат опис од демо сидот каде е достапен.',
                    'city' => $definition['city'],
                    'address' => $definition['address'],
                    'phone' => '+389 2 000 000',
                    'email' => $definition['slug'].'@example.test',
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );

            $sync = [];

            foreach ($definition['doctor_slugs'] as $doctorSlug) {
                $doctor = Doctor::query()->where('slug', $doctorSlug)->first();

                if ($doctor) {
                    $sync[$doctor->id] = [
                        'is_primary' => $doctorSlug === $definition['primary'],
                    ];
                }
            }

            $facility->doctors()->sync($sync);
        }

        Facility::factory()
            ->count(2)
            ->unpublished()
            ->create();
    }
}
