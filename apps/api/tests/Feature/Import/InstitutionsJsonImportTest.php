<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ImportRunStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FacilityMedia;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * import:institutions-json with a synthetic dataset and tiny generated images.
 */
class InstitutionsJsonImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        config(['import.disk' => 'local', 'media.disk' => 'public']);

        $this->dir = sys_get_temp_dir().'/inst-json-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->dir.'/logos');
        File::ensureDirectoryExists($this->dir.'/covers');
        $this->png($this->dir.'/logos/test-bolnica.png', 40, 20, [200, 30, 30]);
        $this->png($this->dir.'/covers/test-bolnica-1.png', 64, 36, [30, 120, 200]);
        $this->png($this->dir.'/covers/test-bolnica-2.png', 64, 36, [30, 200, 120]);
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
     */
    private function dataset(array $overrides = []): string
    {
        $institution = array_merge([
            'slug' => 'test-bolnica',
            'name_mk' => 'ЈЗУ Општа болница Тестово',
            'type' => 'general_hospital',
            'ownership' => 'public',
            'legal_form' => 'ЈЗУ',
            'address' => 'Бул. Болнички 10',
            'town' => 'Тестово',
            'phones' => ['+389 2 000 0000'],
            'email' => 'info@bolnica.invalid',
            'website' => 'https://www.bolnica.invalid/',
            'departments' => ['Кардиологија'],
            'logo' => ['file' => 'logos/test-bolnica.png', 'source_url' => 'https://www.bolnica.invalid/logo.png', 'status' => 'trademark-identification'],
            'covers' => [
                ['file' => 'covers/test-bolnica-1.png', 'source_url' => 'https://www.bolnica.invalid/zgrada.jpg', 'status' => 'needs-permission'],
                ['file' => 'covers/test-bolnica-2.png', 'source_url' => 'https://www.bolnica.invalid/hol.jpg', 'status' => 'needs-permission'],
            ],
            'sources' => ['https://www.bolnica.invalid/za-nas'],
            'confidence' => 'high',
            'workers' => [
                ['full_name' => 'Измислена Кардиоловска', 'title' => 'проф. д-р', 'role' => 'physician', 'specialty' => 'Кардиологија', 'department' => 'Клиника за кардиологија', 'source_url' => 'https://www.bolnica.invalid/tim', 'seen_at' => '2026-10-07', 'confidence' => 'high'],
                ['full_name' => 'Сестра Невидлива', 'role' => 'nurse', 'source_url' => 'https://www.bolnica.invalid/tim'],
                ['full_name' => 'Забко Измислен', 'title' => 'д-р', 'role' => 'dentist', 'source_url' => 'https://www.bolnica.invalid/tim'],
            ],
        ], $overrides);

        $path = $this->dir.'/institutions.json';
        file_put_contents($path, json_encode(['slice' => 'test-slice', 'generated_at' => '2026-10-07T12:00:00Z', 'institutions' => [$institution]], JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function runImport(string $path, bool $dryRun = false): ImportRun
    {
        $this->artisan('import:institutions-json', array_filter(['path' => $path, '--dry-run' => $dryRun]))->assertSuccessful();

        return ImportRun::query()->latest('id')->firstOrFail();
    }

    public function test_published_website_images_reach_the_public_profile_as_logo_and_cover(): void
    {
        $this->runImport($this->dataset());
        $facility = Facility::query()->where('website', 'https://www.bolnica.invalid/')->firstOrFail();

        $this->getJson('/api/v1/facilities/'.$facility->slug)->assertNotFound();

        $facility->forceFill(['is_published' => true, 'published_at' => now()])->save();
        $data = $this->getJson('/api/v1/facilities/'.$facility->slug)->assertOk()->json('data');

        $this->assertSame(Storage::disk('public')->url((string) $facility->avatar_url), $data['avatar_url']);
        $this->assertSame(Storage::disk('public')->url((string) $facility->cover_path), $data['cover_url']);
        // Where the image came from stays internal.
        $this->assertStringNotContainsString('bolnica.invalid/zgrada', (string) json_encode($data));
    }

    public function test_it_creates_hidden_drafts_with_images_and_provenance(): void
    {
        $run = $this->runImport($this->dataset());

        $this->assertSame(ImportRunStatus::Succeeded, $run->status, (string) $run->error);
        $facility = Facility::query()->where('website', 'https://www.bolnica.invalid/')->firstOrFail();
        $this->assertFalse($facility->is_published);
        $this->assertSame('hospital', $facility->type->value);
        $this->assertSame('+389 2 000 0000', $facility->phone);

        // Logo → avatar; first cover candidate → cover; the second kept as an alternative.
        $media = $facility->media;
        $this->assertCount(3, $media);
        $logo = $media->firstWhere('kind', FacilityMedia::KIND_LOGO);
        $this->assertSame(FacilityMedia::STATUS_TRADEMARK, $logo?->status);
        $this->assertSame($logo?->path, $facility->avatar_url);
        $covers = $media->where('kind', FacilityMedia::KIND_COVER)->values();
        $this->assertSame('https://www.bolnica.invalid/zgrada.jpg', $covers[0]->source_url);
        $this->assertSame(FacilityMedia::STATUS_WEBSITE, $covers[0]->status);
        $this->assertSame($covers[0]->path, $facility->cover_path);
        Storage::disk('public')->assertExists((string) $facility->cover_path);
        Storage::disk('public')->assertExists((string) $covers[1]->path);

        // Per-field source URL.
        $this->assertSame('https://www.bolnica.invalid/za-nas', FieldProvenance::query()->where('subject_type', 'facility')->where('field', 'phone')->value('source_url'));
        $this->assertSame('https://www.bolnica.invalid/zgrada.jpg', FieldProvenance::query()->where('subject_type', 'facility')->where('field', 'cover_path')->value('source_url'));

        // Doctors: physician + dentist as hidden drafts; the nurse is skipped.
        $this->assertSame(1, $run->count('doctors_created'));
        $this->assertSame(1, $run->count('dentists_created'));
        $this->assertSame(1, $run->count('workers_excluded_role'));
        $this->assertSame(0, Doctor::query()->where('full_name', 'like', '%Невидлива%')->count());
        $doctor = Doctor::query()->where('full_name', 'Измислена Кардиоловска')->firstOrFail();
        $this->assertFalse($doctor->is_published);
        $this->assertSame('проф. д-р', $doctor->title);
        $this->assertSame(['kardiologija'], $doctor->specialties->pluck('slug')->all());
        $this->assertSame('Клиника за кардиологија', DB::table('doctor_facility')->where('doctor_id', $doctor->getKey())->value('work_unit'));
        $item = ImportReviewItem::query()->where('kind', ImportReviewKind::New)->where('subject_type', 'doctor')->where('subject_id', $doctor->getKey())->firstOrFail();
        $this->assertTrue($item->details['needs_licence_verification']);
    }

    public function test_re_running_is_idempotent_and_a_dry_run_stores_nothing(): void
    {
        $path = $this->dataset();
        $dry = $this->runImport($path, dryRun: true);

        $this->assertSame(0, Facility::query()->count());
        $this->assertSame(0, Doctor::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(1, $dry->count('logos_would_store'));
        $this->assertSame(2, $dry->count('covers_would_store'));

        $this->runImport($path);
        $counts = [Facility::query()->count(), Doctor::query()->count(), FacilityMedia::query()->count(), count(Storage::disk('public')->allFiles()), ImportReviewItem::query()->count()];
        $second = $this->runImport($path);

        $this->assertSame($counts, [Facility::query()->count(), Doctor::query()->count(), FacilityMedia::query()->count(), count(Storage::disk('public')->allFiles()), ImportReviewItem::query()->count()]);
        $this->assertSame(1, $second->count('facilities_unchanged'));
    }

    public function test_it_matches_an_existing_register_facility_and_only_fills_gaps(): void
    {
        $facility = Facility::factory()->create([
            'name' => 'ЈЗУ Општа Болница Тестово', 'city' => 'Тестово', 'type' => 'hospital',
            'phone' => null, 'email' => null, 'website' => null, 'address' => 'Регистарска адреса 1', 'fzo_code' => '9000010',
        ]);
        $existing = Doctor::factory()->create(['full_name' => 'Измислена Кардиоловска', 'fzo_facsimile' => '900020']);
        $existing->facilities()->attach($facility->getKey());

        $run = $this->runImport($this->dataset());

        $this->assertSame(1, $run->count('facilities_matched'));
        $this->assertSame(1, Facility::query()->count());
        $facility->refresh();
        $this->assertSame('Регистарска адреса 1', $facility->address, 'The register address is not replaced by the website one.');
        $this->assertSame('+389 2 000 0000', $facility->phone);
        $this->assertSame(1, $run->count('doctors_matched'));
        $this->assertSame(1, Doctor::query()->where('name_key', 'ИЗМИСЛЕНА КАРДИОЛОВСКА')->count());
        // Only a value someone else set and the website disagrees with is raised.
        $conflicts = ImportReviewItem::query()->where('kind', ImportReviewKind::Conflict)->get();
        $this->assertSame(['doctor:'.$existing->getKey().':title'], $conflicts->pluck('item_key')->all());
        $this->assertSame('д-р', $existing->refresh()->title);
    }

    public function test_register_names_with_municipality_and_quotes_still_match(): void
    {
        $city = Facility::factory()->create(['name' => 'ЈЗУ Градска Општа Болница 8 Ми Септември', 'city' => 'Скопје - Карпош', 'type' => 'hospital', 'website' => null, 'phone' => null]);
        $clinic = Facility::factory()->create(['name' => 'ЈЗУ Универзитетска Клиника за Кардиологија', 'city' => 'Скопје - Центар', 'type' => 'hospital', 'website' => null]);

        $run = $this->runImport($this->dataset(['name_mk' => 'ЈЗУ Градска општа болница „8-ми Септември“ – Скопје', 'town' => 'Скопје', 'website' => null, 'workers' => []]));
        $this->assertSame(1, $run->count('facilities_matched'));
        $this->assertSame(0, $run->count('facilities_matched_by_partial_name'));
        $this->assertSame('+389 2 000 0000', $city->refresh()->phone);

        $run = $this->runImport($this->dataset([
            'slug' => 'kardio', 'name_mk' => 'ЈЗУ Универзитетска клиника за кардиологија и кардиоваскуларна хирургија – Скопје',
            'town' => 'Скопје', 'website' => null, 'workers' => [], 'logo' => null, 'covers' => [],
        ]));
        $this->assertSame(1, $run->count('facilities_matched_by_partial_name'));
        $this->assertSame(2, Facility::query()->count());
        $this->assertSame(1, ImportReviewItem::query()->where('item_key', 'like', 'website-facility-partial:%')->where('subject_id', $clinic->getKey())->count());
    }

    public function test_staff_can_switch_and_take_down_images_and_a_re_import_respects_it(): void
    {
        $path = $this->dataset();
        $this->runImport($path);
        $facility = Facility::query()->firstOrFail();
        $staff = User::factory()->create();
        [$first, $second] = $facility->media->where('kind', FacilityMedia::KIND_COVER)->values()->all();

        $second->makeCurrentCover($staff);
        $this->assertSame($second->path, $facility->refresh()->cover_path);
        Storage::disk('public')->assertExists((string) $first->path);

        $second->refresh()->remove($staff);
        $facility->refresh();
        $this->assertNull($facility->cover_path);
        $this->assertSame(FacilityMedia::STATUS_REMOVED, $second->refresh()->status);
        $this->assertNull($second->path);
        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'facility_media')->where('description', 'image removed')->count());

        $this->runImport($path);

        $this->assertNull($facility->refresh()->cover_path, 'A taken-down cover is not replaced by the next candidate.');
        $this->assertSame(FacilityMedia::STATUS_REMOVED, $second->refresh()->status, 'A removed image is never re-added.');
    }
}
