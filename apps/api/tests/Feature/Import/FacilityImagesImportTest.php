<?php

namespace Tests\Feature\Import;

use App\Models\Facility;
use App\Models\FacilityMedia;
use App\Models\FieldProvenance;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Import\ProvenanceWriter;
use App\Support\Import\Website\FacilityImagesImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * import:facility-images with a synthetic images.json and generated images.
 */
class FacilityImagesImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        config(['import.disk' => 'local', 'media.disk' => 'public']);

        $this->dir = sys_get_temp_dir().'/facility-images-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->dir.'/logos');
        File::ensureDirectoryExists($this->dir.'/covers');
        $this->png($this->dir.'/logos/logo.png', 96, 96, [200, 30, 30]);
        $this->png($this->dir.'/covers/cover-1.png', 800, 450, [30, 120, 200]);
        $this->png($this->dir.'/covers/cover-2.png', 800, 450, [30, 200, 120]);
        $this->png($this->dir.'/covers/tiny.png', 300, 200, [10, 10, 10]);
        $this->png($this->dir.'/logos/tiny.png', 32, 32, [10, 10, 10]);
        file_put_contents($this->dir.'/logos/fake.png', 'not an image at all');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function png(string $path, int $width, int $height, array $rgb): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, ...$rgb));
        imagepng($image, $path);
        imagedestroy($image);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function entry(Facility $facility, array $overrides = []): array
    {
        return array_merge([
            'facility_id' => $facility->getKey(),
            'name' => $facility->name,
            'confidence' => 'high',
            'official_url' => 'https://www.klinika.invalid/',
            'logo' => ['file' => 'logos/logo.png', 'source_url' => 'https://www.klinika.invalid/logo.png'],
            'covers' => [
                ['file' => 'covers/cover-1.png', 'source_url' => 'https://www.klinika.invalid/zgrada.jpg'],
                ['file' => 'covers/cover-2.png', 'source_url' => 'https://www.klinika.invalid/hol.jpg'],
            ],
        ], $overrides);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function runImport(array $entries, bool $dryRun = false): ImportRun
    {
        $path = $this->dir.'/images.json';
        file_put_contents($path, json_encode(['slice' => 'test', 'generated_at' => '2026-10-23T12:00:00Z', 'facilities' => $entries], JSON_UNESCAPED_UNICODE));

        $this->artisan('import:facility-images', array_filter(['path' => $path, '--dry-run' => $dryRun]))->assertSuccessful();

        return ImportRun::query()->where('source', 'website')->latest('id')->firstOrFail();
    }

    private function clinic(array $attributes = []): Facility
    {
        return Facility::factory()->create(array_merge([
            'name' => 'ПЗУ Поликлиника Тестово Здравје',
            'city' => 'Скопје',
            'website' => null,
            'avatar_url' => null,
            'cover_path' => null,
        ], $attributes));
    }

    public function test_it_fills_logo_cover_and_website_with_provenance(): void
    {
        $facility = $this->clinic();

        $run = $this->runImport([$this->entry($facility)]);
        $facility->refresh();

        $this->assertNotNull($facility->avatar_url);
        $this->assertNotNull($facility->cover_path);
        Storage::disk('public')->assertExists((string) $facility->avatar_url);
        Storage::disk('public')->assertExists((string) $facility->cover_path);
        $this->assertSame('https://www.klinika.invalid/', $facility->website);

        $logo = $facility->media->firstWhere('kind', FacilityMedia::KIND_LOGO);
        $this->assertSame(FacilityMedia::STATUS_TRADEMARK, $logo?->status);
        $this->assertSame('https://www.klinika.invalid/logo.png', $logo?->source_url);
        $this->assertSame(2, $facility->media->where('kind', FacilityMedia::KIND_COVER)->where('status', FacilityMedia::STATUS_WEBSITE)->count());
        $this->assertSame(
            'https://www.klinika.invalid/zgrada.jpg',
            $facility->media->firstWhere('path', $facility->cover_path)?->source_url,
            'The first cover becomes the cover.',
        );

        $provenance = FieldProvenance::query()->where('subject_type', FieldProvenance::SUBJECT_FACILITY)->where('subject_id', $facility->getKey())->get()->keyBy('field');
        $this->assertSame('website', $provenance['website']->source);
        $this->assertSame('website', $provenance['avatar_url']->source);
        $this->assertSame('https://www.klinika.invalid/logo.png', $provenance['avatar_url']->source_url);

        $this->assertSame(1, $run->count('logos_filled'));
        $this->assertSame(1, $run->count('covers_filled'));
        $this->assertSame(1, $run->count('websites_filled'));

        // Running it again changes nothing.
        $again = $this->runImport([$this->entry($facility)]);
        $this->assertSame(0, $again->count('logos_stored') + $again->count('covers_stored'));
        $this->assertSame(3, FacilityMedia::query()->count());
    }

    public function test_it_never_replaces_an_existing_logo_cover_or_website(): void
    {
        $facility = $this->clinic(['avatar_url' => 'facilities/staff-logo.png', 'cover_path' => 'facilities/covers/staff.webp', 'website' => 'https://staff.invalid/']);

        $run = $this->runImport([$this->entry($facility)]);
        $facility->refresh();

        $this->assertSame('facilities/staff-logo.png', $facility->avatar_url);
        $this->assertSame('facilities/covers/staff.webp', $facility->cover_path);
        $this->assertSame('https://staff.invalid/', $facility->website);
        $this->assertSame(0, FacilityMedia::query()->count(), 'Nothing is stored for a field that is already filled.');
        $this->assertSame(1, $run->count('logos_skipped_existing'));
        $this->assertSame(1, $run->count('covers_skipped_existing'));
    }

    public function test_a_locked_field_is_not_filled(): void
    {
        $facility = $this->clinic();
        ProvenanceWriter::setLock($facility, 'avatar_url', true, User::factory()->create());
        ProvenanceWriter::setLock($facility, 'website', true, User::factory()->create());

        $run = $this->runImport([$this->entry($facility)]);

        $this->assertNull($facility->refresh()->avatar_url);
        $this->assertNotNull($facility->cover_path);
        $this->assertNull($facility->website);
        $this->assertSame(1, $run->count('logos_skipped_locked'));
        $this->assertSame(0, FacilityMedia::query()->where('kind', FacilityMedia::KIND_LOGO)->count());
    }

    public function test_a_removed_image_never_comes_back(): void
    {
        $facility = $this->clinic();
        $this->runImport([$this->entry($facility)]);
        $staff = User::factory()->create();

        $logo = $facility->refresh()->media->firstWhere('kind', FacilityMedia::KIND_LOGO);
        $logo?->remove($staff);
        $cover = $facility->refresh()->media->firstWhere('path', $facility->cover_path);
        $cover?->remove($staff);
        $this->assertNull($facility->refresh()->avatar_url);
        $this->assertNull($facility->cover_path);

        // Same bytes again (and the takedown locked the fields).
        $this->runImport([$this->entry($facility)]);
        $this->assertNull($facility->refresh()->avatar_url);
        $this->assertNull($facility->cover_path);

        // Even with the lock lifted and a different file under the same
        // address, the removed image stays removed.
        ProvenanceWriter::setLock($facility, 'avatar_url', false, $staff);
        $this->png($this->dir.'/logos/logo.png', 96, 96, [1, 2, 3]);
        $run = $this->runImport([$this->entry($facility, ['covers' => []])]);

        $this->assertNull($facility->refresh()->avatar_url);
        $this->assertSame(1, $run->count('images_skipped_removed'));
        $this->assertSame(1, FacilityMedia::query()->where('kind', FacilityMedia::KIND_LOGO)->count());
    }

    public function test_a_pharmacy_gets_its_images(): void
    {
        $pharmacy = Facility::factory()->pharmacy()->create(['name' => 'ПЗУ Аптека Тестофарм', 'city' => 'Битола', 'avatar_url' => null, 'cover_path' => null, 'website' => null]);

        $this->runImport([$this->entry($pharmacy, ['name' => 'Аптека Тестофарм Битола', 'official_url' => 'https://www.facebook.com/testofarm'])]);
        $pharmacy->refresh();

        $this->assertNotNull($pharmacy->avatar_url);
        $this->assertNotNull($pharmacy->cover_path);
        $this->assertNull($pharmacy->website, 'A social page does not become the website.');
        $this->assertSame(0, Facility::query()->where('id', '!=', $pharmacy->getKey())->count(), 'No draft is created.');
    }

    public function test_low_confidence_is_reported_for_review_and_not_imported(): void
    {
        $facility = $this->clinic();

        $run = $this->runImport([$this->entry($facility, ['confidence' => 'medium'])]);

        $this->assertNull($facility->refresh()->avatar_url);
        $this->assertNull($facility->website);
        $this->assertSame(0, FacilityMedia::query()->count());
        $this->assertSame(1, $run->count('needs_review'));
    }

    public function test_a_name_mismatch_or_a_missing_or_merged_facility_is_skipped(): void
    {
        $facility = $this->clinic();
        $kept = $this->clinic(['name' => 'ЈЗУ Општа болница Тестово']);
        $merged = $this->clinic(['name' => 'Општа болница Тестово', 'merged_into_id' => $kept->getKey()]);
        $merged->delete();

        $run = $this->runImport([
            $this->entry($facility, ['name' => 'ПЗУ Дентален центар Насмевка']),
            $this->entry($merged),
            ['facility_id' => 999999, 'name' => 'Непостоечка', 'confidence' => 'high', 'logo' => ['file' => 'logos/logo.png'], 'covers' => []],
        ]);

        $this->assertSame(0, FacilityMedia::query()->count());
        $this->assertNull($kept->refresh()->avatar_url, 'A merged entry is not moved to the facility that stayed.');
        $this->assertSame(1, $run->count('names_mismatched'));
        $this->assertSame(1, $run->count('facilities_merged_or_deleted'));
        $this->assertSame(1, $run->count('facilities_missing'));
    }

    public function test_invalid_or_too_small_files_are_skipped(): void
    {
        $facility = $this->clinic();

        $run = $this->runImport([$this->entry($facility, [
            'logo' => ['file' => 'logos/fake.png', 'source_url' => 'https://www.klinika.invalid/fake.png'],
            'covers' => [
                ['file' => 'covers/tiny.png', 'source_url' => 'https://www.klinika.invalid/tiny.jpg'],
                ['file' => '../outside.png', 'source_url' => 'https://www.klinika.invalid/x.jpg'],
            ],
        ])]);

        $this->assertNull($facility->refresh()->avatar_url);
        $this->assertNull($facility->cover_path);
        $this->assertSame(0, FacilityMedia::query()->count());
        $this->assertSame(1, $run->count('images_not_an_image'));
        $this->assertSame(1, $run->count('images_too_small'));
        $this->assertSame(1, $run->count('images_missing_file'));

        // A null byte in a path is refused, not fatal for the run.
        $null = $this->runImport([$this->entry($facility, ['logo' => ['file' => "logos/logo.png\0.txt"], 'covers' => []])]);
        $this->assertSame(1, $null->count('images_missing_file'));

        $small = $this->runImport([$this->entry($facility, ['logo' => ['file' => 'logos/tiny.png'], 'covers' => []])]);
        $this->assertSame(1, $small->count('images_too_small'));
    }

    public function test_a_dry_run_stores_nothing(): void
    {
        $facility = $this->clinic();

        $run = $this->runImport([$this->entry($facility)], dryRun: true);

        $this->assertTrue($run->dry_run);
        $this->assertNull($facility->refresh()->avatar_url);
        $this->assertNull($facility->cover_path);
        $this->assertNull($facility->website);
        $this->assertSame(0, FacilityMedia::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(1, $run->count('logos_would_store'));
        $this->assertSame(2, $run->count('covers_would_store'));
    }

    public function test_names_match_roughly(): void
    {
        $this->assertTrue(FacilityImagesImporter::namesMatch('Поликлиника Тестово Здравје', 'ПЗУ Поликлиника „Тестово Здравје“ ДООЕЛ Скопје', 'Скопје'));
        $this->assertTrue(FacilityImagesImporter::namesMatch('PZU Apteka Zegin', 'ПЗУ Аптека Зегин', null));
        $this->assertFalse(FacilityImagesImporter::namesMatch('Дентален центар Насмевка', 'Поликлиника Тестово Здравје', 'Скопје'));
        $this->assertTrue(FacilityImagesImporter::namesMatch('Аптека Битола', 'ПЗУ Аптека Битола', 'Битола'));
        // Generic words alone or differing branch numbers do not match.
        $this->assertFalse(FacilityImagesImporter::namesMatch('Аптека', 'ПЗУ Аптека Мега Битола', 'Битола'));
        $this->assertFalse(FacilityImagesImporter::namesMatch('Аптека Зегин', 'ПЗУ Аптека Битола', 'Битола'));
        $this->assertFalse(FacilityImagesImporter::namesMatch('Аптека Јанев', 'ПЗУ Аптека', 'Охрид'));
        $this->assertFalse(FacilityImagesImporter::namesMatch('ПЗУ Аптека Зегин 5', 'ПЗУ Аптека Зегин 12', null));
        $this->assertTrue(FacilityImagesImporter::namesMatch('Аптека Зегин 5', 'ПЗУ Аптека Зегин 5 Скопје', 'Скопје'));
    }
}
