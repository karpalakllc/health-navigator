<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SiteSettingsSeeder::class,
            RolesAndPermissionsSeeder::class,
            PlatformUserSeeder::class,
            DoctorDirectorySeeder::class,
            FacilityDirectorySeeder::class,
            // Before RichDemoSeeder, which enriches (and adds covers to) the
            // pharmacies too and skips rows that do not exist yet.
            PharmacyCatalogSeeder::class,
            RichDemoSeeder::class,
            ReviewSeeder::class,
            ForumSeeder::class,
            TriageSeeder::class,
            TriageFlowSeeder::class,
        ]);
    }
}
