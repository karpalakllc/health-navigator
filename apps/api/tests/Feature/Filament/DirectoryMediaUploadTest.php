<?php

namespace Tests\Feature\Filament;

use App\Enums\FacilityType;
use App\Enums\UserKind;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Facilities\Pages\EditFacility;
use App\Filament\Resources\Pharmacies\Pages\EditPharmacy;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Facility/pharmacy cover images and doctor photos go through the media
 * pipeline (ImageOptimizer: re-encoded WebP on the media disk), the API
 * returns them as URLs, and a replaced or cleared file is deleted.
 */
class DirectoryMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media.disk' => 'public', 'media.directory' => 'media']);

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current()->update(['public_pharmacies' => true]);

        $admin = User::factory()->create(['user_kind' => UserKind::Staff]);
        $admin->syncRoles(['Administrator']);
        $this->actingAs($admin);
    }

    private function uploadCover(Facility $facility, UploadedFile $file, string $page = EditFacility::class): Facility
    {
        Livewire::test($page, ['record' => $facility->getRouteKey()])
            ->fillForm(['cover_path' => [$file]])
            ->call('save')
            ->assertHasNoFormErrors();

        return $facility->refresh();
    }

    public function test_facility_cover_is_stored_as_bounded_webp_and_exposed_as_a_url(): void
    {
        $facility = Facility::factory()->create(['slug' => 'klinika', 'type' => FacilityType::Clinic]);

        $facility = $this->uploadCover($facility, UploadedFile::fake()->image('cover.jpg', 2400, 1200));

        $this->assertMatchesRegularExpression('#^media/facilities/covers/[0-9a-f-]{36}\.webp$#', $facility->cover_path);
        Storage::disk('public')->assertExists($facility->cover_path);

        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($facility->cover_path));
        $this->assertSame(IMAGETYPE_WEBP, $type);
        $this->assertSame([1600, 800], [$width, $height]);

        $url = Storage::disk('public')->url($facility->cover_path);

        $this->getJson('/api/v1/facilities/klinika')->assertOk()->assertJsonPath('data.cover_url', $url);
        $this->getJson('/api/v1/facilities')->assertOk()->assertJsonPath('data.0.cover_url', $url);
    }

    public function test_replacing_or_clearing_the_cover_deletes_the_old_file(): void
    {
        $facility = Facility::factory()->create(['type' => FacilityType::Hospital]);

        $first = $this->uploadCover($facility, UploadedFile::fake()->image('a.png', 800, 450))->cover_path;
        $second = $this->uploadCover($facility, UploadedFile::fake()->image('b.jpg', 800, 450))->cover_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        Livewire::test(EditFacility::class, ['record' => $facility->getRouteKey()])
            ->fillForm(['cover_path' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($facility->refresh()->cover_path);
        Storage::disk('public')->assertMissing($second);
        $this->getJson('/api/v1/facilities/'.$facility->slug)->assertJsonPath('data.cover_url', null);
    }

    public function test_a_non_image_cover_is_rejected(): void
    {
        $facility = Facility::factory()->create(['type' => FacilityType::Clinic]);

        Livewire::test(EditFacility::class, ['record' => $facility->getRouteKey()])
            ->fillForm(['cover_path' => [UploadedFile::fake()->create('brochure.pdf', 20, 'application/pdf')]])
            ->call('save')
            ->assertHasFormErrors(['cover_path']);

        $this->assertNull($facility->refresh()->cover_path);
        $this->assertSame([], Storage::disk('public')->allFiles('media'));
    }

    public function test_a_gif_cover_is_rejected_as_raster_formats_are_jpg_png_webp_only(): void
    {
        $facility = Facility::factory()->create(['type' => FacilityType::Clinic]);

        Livewire::test(EditFacility::class, ['record' => $facility->getRouteKey()])
            ->fillForm(['cover_path' => [UploadedFile::fake()->image('anim.gif', 400, 200)]])
            ->call('save')
            ->assertHasFormErrors(['cover_path']);

        $this->assertNull($facility->refresh()->cover_path);
    }

    public function test_pharmacy_cover_is_uploaded_and_listed(): void
    {
        $pharmacy = Facility::factory()->pharmacy()->create(['slug' => 'apteka']);

        $pharmacy = $this->uploadCover($pharmacy, UploadedFile::fake()->image('p.webp', 1000, 600), EditPharmacy::class);

        $this->assertStringStartsWith('media/pharmacies/covers/', $pharmacy->cover_path);
        $url = Storage::disk('public')->url($pharmacy->cover_path);

        $this->getJson('/api/v1/pharmacies')->assertOk()->assertJsonPath('data.0.cover_url', $url);
        $this->getJson('/api/v1/pharmacies/apteka')->assertOk()->assertJsonPath('data.cover_url', $url);
    }

    public function test_doctor_photo_upload_is_served_from_the_media_disk_and_replacing_it_deletes_the_old_file(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana', 'avatar_url' => 'https://example.org/legacy.jpg']);

        $upload = function (UploadedFile $file) use ($doctor): string {
            Livewire::test(EditDoctor::class, ['record' => $doctor->getRouteKey()])
                ->fillForm(['avatar_url' => [$file]])
                ->call('save')
                ->assertHasNoFormErrors();

            return $doctor->refresh()->avatar_url;
        };

        $first = $upload(UploadedFile::fake()->image('a.jpg', 300, 300));
        $this->assertMatchesRegularExpression('#^media/doctors/[0-9a-f-]{36}\.webp$#', $first);
        Storage::disk('public')->assertExists($first);

        $this->getJson('/api/v1/doctors/ana')->assertJsonPath('data.avatar_url', Storage::disk('public')->url($first));
        $this->getJson('/api/v1/doctors')->assertJsonPath('data.0.avatar_url', Storage::disk('public')->url($first));

        $second = $upload(UploadedFile::fake()->image('b.png', 300, 300));
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_only_files_under_the_media_directory_are_ever_deleted(): void
    {
        Storage::disk('public')->put('elsewhere/keep.webp', 'x');
        $doctor = Doctor::factory()->create(['avatar_url' => 'elsewhere/keep.webp']);

        $doctor->update(['avatar_url' => null]);
        $doctor->forceDelete();

        Storage::disk('public')->assertExists('elsewhere/keep.webp');
    }

    public function test_force_deleting_a_facility_removes_its_media_but_soft_deleting_keeps_it(): void
    {
        Storage::disk('public')->put('media/facilities/logo.webp', 'x');
        Storage::disk('public')->put('media/facilities/covers/cover.webp', 'x');
        $facility = Facility::factory()->create([
            'avatar_url' => 'media/facilities/logo.webp',
            'cover_path' => 'media/facilities/covers/cover.webp',
        ]);

        $facility->delete();
        Storage::disk('public')->assertExists('media/facilities/covers/cover.webp');

        $facility->forceDelete();
        Storage::disk('public')->assertMissing('media/facilities/logo.webp');
        Storage::disk('public')->assertMissing('media/facilities/covers/cover.webp');
    }
}
