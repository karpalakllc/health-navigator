<?php

namespace Database\Seeders;

use App\Enums\FacilityType;
use App\Models\Facility;
use App\Models\Product;
use Database\Seeders\Concerns\SeedsLocalDemoData;
use Illuminate\Database\Seeder;

class PharmacyCatalogSeeder extends Seeder
{
    use SeedsLocalDemoData;

    public function run(): void
    {
        if (! $this->shouldRunLocalDemoSeeders()) {
            $this->command?->warn('PharmacyCatalogSeeder skipped. Use APP_ENV=local (or development), or SEED_LOCAL_DEMO=true, then php artisan db:seed');

            return;
        }

        $pharmacies = [
            [
                'name' => 'Еурофарм Скопје',
                'slug' => 'eurofarm-skopje',
                'city' => 'Скопје',
                'address' => 'ул. Македонија 1',
            ],
            [
                'name' => 'Фарма Медика Битола',
                'slug' => 'farma-medika-bitola',
                'city' => 'Битола',
                'address' => 'ул. Широк Сокак 10',
            ],
            [
                'name' => 'Аптека Охрид',
                'slug' => 'apteka-ohrid',
                'city' => 'Охрид',
                'address' => 'ул. Климент Охридски 5',
            ],
        ];

        $pharmacyModels = collect($pharmacies)->map(function (array $definition) {
            return Facility::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'type' => FacilityType::Pharmacy,
                    'description' => 'Аптека за локален развој на каталог — цените се информативни.',
                    'city' => $definition['city'],
                    'address' => $definition['address'],
                    'phone' => '+389 2 310 0000',
                    'email' => $definition['slug'].'@example.test',
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );
        });

        $products = [
            [
                'slug' => 'paracetamol-500',
                'name' => 'Парацетамол 500 mg',
                'category' => 'Аналгетици и антипиретици',
                'description' => 'Стандарден аналгетик и антипиретик. Користете го само по препорака или упатство на пакувањето; доколку симптомите траат, консултирајте лекар.',
                'offers' => [
                    'eurofarm-skopje' => 120,
                    'farma-medika-bitola' => 115,
                    'apteka-ohrid' => 125,
                ],
            ],
            [
                'slug' => 'vitamin-c-1000',
                'name' => 'Витамин Ц 1000 mg',
                'category' => 'Витамини и минерали',
                'description' => 'Додаток на исхрана — не ја заменува разновидната исхрана. Чувајте надвор од достиг на деца.',
                'offers' => [
                    'eurofarm-skopje' => 350,
                    'apteka-ohrid' => 340,
                    'farma-medika-bitola' => 355,
                ],
            ],
            [
                'slug' => 'ibuprofen-400',
                'name' => 'Ибупрофен 400 mg',
                'category' => 'Аналгетици и антиинфламаторици',
                'description' => 'НСПВЛ. Не прекорачувајте ја препорачаната доза. Препорачливо при попречувања на желудникот само по лекарски совет.',
                'offers' => [
                    'eurofarm-skopje' => 180,
                    'farma-medika-bitola' => 175,
                    'apteka-ohrid' => 182,
                ],
            ],
            [
                'slug' => 'loratadin-10',
                'name' => 'Лоратадин 10 mg',
                'category' => 'Антихистаминици',
                'description' => 'Симптоми на алергија (кашлица од полен, кивање). Неседирачки антихистаминик кај многу пациенти — индивидуално делување.',
                'offers' => [
                    'eurofarm-skopje' => 210,
                    'farma-medika-bitola' => 199,
                    'apteka-ohrid' => 205,
                ],
            ],
            [
                'slug' => 'omeprazol-20',
                'name' => 'Омепразол 20 mg',
                'category' => 'Гастро заштита',
                'description' => 'Инхибитор на протонска пумпа за краткотрајна употреба по препорака. Долгорочна терапија само под надзор на лекар.',
                'offers' => [
                    'eurofarm-skopje' => 290,
                    'apteka-ohrid' => 285,
                ],
            ],
            [
                'slug' => 'oralna-rehidracija',
                'name' => 'Орална рехидрациска сол',
                'category' => 'Педијатрија и прва помош',
                'description' => 'Раствор за дополнување течности при дијареја или повраќање (информативно). При дехидрација кај деца — контакт со педијатар.',
                'offers' => [
                    'eurofarm-skopje' => 145,
                    'farma-medika-bitola' => 139,
                    'apteka-ohrid' => 149,
                ],
            ],
        ];

        foreach ($products as $definition) {
            $product = Product::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'category' => $definition['category'],
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );

            foreach ($definition['offers'] as $pharmacySlug => $price) {
                $pharmacy = $pharmacyModels->firstWhere('slug', $pharmacySlug);

                if ($pharmacy) {
                    $pharmacy->products()->syncWithoutDetaching([
                        $product->id => [
                            'price' => $price,
                            'currency' => 'MKD',
                            'is_available' => true,
                            'price_updated_at' => now(),
                        ],
                    ]);
                }
            }
        }

        Product::factory()->unpublished()->create([
            'name' => 'Необјавен производ',
            'slug' => 'neobjaven-proizvod',
        ]);
    }
}
