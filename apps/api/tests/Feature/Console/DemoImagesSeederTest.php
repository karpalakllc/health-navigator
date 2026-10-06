<?php

namespace Tests\Feature\Console;

use App\Models\Doctor;
use App\Models\Facility;
use Database\Seeders\DoctorDirectorySeeder;
use Database\Seeders\FacilityDirectorySeeder;
use Database\Seeders\PharmacyCatalogSeeder;
use Database\Seeders\RichDemoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The demo directory gets photos and covers from the committed Unsplash
 * assets (no network), copied onto the media disk like uploads — and never in
 * a deployed environment, since they show real people under fictional names.
 */
class DemoImagesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media.disk' => 'public', 'media.directory' => 'media']);
    }

    private function seedDemo(): void
    {
        $this->seed([
            RolesAndPermissionsSeeder::class,
            DoctorDirectorySeeder::class,
            FacilityDirectorySeeder::class,
            PharmacyCatalogSeeder::class,
            RichDemoSeeder::class,
        ]);
    }

    public function test_every_demo_profile_with_an_asset_gets_a_media_disk_image(): void
    {
        $data = require database_path('seeders/data/rich-profiles.php');

        $this->seedDemo();

        foreach ($data['doctors'] as $slug => $profile) {
            $path = Doctor::query()->where('slug', $slug)->value('avatar_url');

            $this->assertMatchesRegularExpression('#^media/demo/doctors/[0-9a-f-]{36}\.webp$#', (string) $path, $slug);
            Storage::disk('public')->assertExists($path);
            $this->assertFileExists(database_path('seeders/assets/unsplash/'.$profile['photo']));
        }

        foreach ($data['facilities'] as $slug => $profile) {
            $facility = Facility::query()->where('slug', $slug)->firstOrFail();
            $folder = $facility->isPharmacy() ? 'pharmacies' : 'facilities';

            $this->assertStringStartsWith("media/demo/{$folder}/covers/", (string) $facility->cover_path, $slug);
            Storage::disk('public')->assertExists($facility->cover_path);
        }

        $this->getJson('/api/v1/doctors/'.array_key_first($data['doctors']))
            ->assertJsonPath('data.avatar_url', Storage::disk('public')->url(
                Doctor::query()->where('slug', array_key_first($data['doctors']))->value('avatar_url'),
            ));
    }

    public function test_reseeding_reuses_the_copies_and_prunes_orphans(): void
    {
        $this->seedDemo();
        $files = Storage::disk('public')->allFiles('media/demo');
        Storage::disk('public')->put('media/demo/doctors/orphan.webp', 'x');

        $this->seed(RichDemoSeeder::class);

        $this->assertSame($files, Storage::disk('public')->allFiles('media/demo'));
    }

    public function test_some_demo_doctors_and_facilities_are_featured(): void
    {
        $this->seedDemo();

        $this->assertSame(3, Doctor::query()->where('is_featured', true)->count());
        $this->assertGreaterThanOrEqual(2, Facility::query()->clinical()->where('is_featured', true)->count());

        // Featured lead the default name order, so the effect is visible.
        $first = $this->getJson('/api/v1/doctors?per_page=3')->json('data');
        $this->assertSame([true, true, true], array_column($first, 'is_featured'));
    }

    public function test_no_images_are_attached_in_a_deployed_environment_even_with_seed_local_demo(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        config(['zdravje.seed.local_demo' => true]);

        $this->seedDemo();

        $this->assertGreaterThan(0, Doctor::query()->count());
        $this->assertSame(0, Doctor::query()->whereNotNull('avatar_url')->count());
        $this->assertSame(0, Facility::query()->whereNotNull('cover_path')->count());
        $this->assertSame([], Storage::disk('public')->allFiles('media'));
    }
}
