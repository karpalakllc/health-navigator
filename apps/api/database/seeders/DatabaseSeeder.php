<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlatformUserSeeder::class,
            DoctorDirectorySeeder::class,
            FacilityDirectorySeeder::class,
            RichDemoSeeder::class,
            ReviewSeeder::class,
            PharmacyCatalogSeeder::class,
            ForumSeeder::class,
            TriageSeeder::class,
        ]);
    }
}
