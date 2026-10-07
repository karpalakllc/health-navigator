<?php

namespace Tests\Feature\Import;

use App\Enums\ImportRunStatus;
use App\Models\Doctor;
use App\Models\ImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Downloading the ФЗОМ files: robots.txt, User-Agent, conditional GET,
 * private snapshots with retention.
 */
class FzomFetchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        config([
            'import.disk' => 'local',
            'import.request_delay_seconds' => 0,
            'import.contact' => 'data@example.test',
            'import.fzom.files' => [
                'pzz' => 'https://registry.test/XML/pzz.xml',
                'spec' => 'https://registry.test/XML/spec.xml',
            ],
        ]);
    }

    /** Replaces the specialist file's body when set. */
    private ?string $specBody = null;

    private function fakeRegistry(bool $notModified = false, string $robots = ''): void
    {
        Http::fake(function (Request $request) use ($notModified, $robots) {
            if (str_ends_with($request->url(), '/robots.txt')) {
                return $robots === '' ? Http::response('', 404) : Http::response($robots);
            }

            if ($notModified && $request->hasHeader('If-None-Match')) {
                return Http::response('', 304);
            }

            $file = str_contains($request->url(), 'pzz') ? 'pzz' : 'spec';

            $body = $file === 'spec' && $this->specBody !== null ? $this->specBody : (string) file_get_contents(base_path("tests/Fixtures/import/fzom/{$file}.xml"));

            return Http::response($body, 200, [
                'ETag' => '"'.$file.'-v1"',
                'Last-Modified' => 'Tue, 06 Oct 2026 15:00:00 GMT',
            ]);
        });
    }

    public function test_it_downloads_politely_and_skips_an_unchanged_source(): void
    {
        $this->fakeRegistry(notModified: true);

        $this->artisan('import:fzom')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'pzz.xml')
            && str_contains($request->header('User-Agent')[0] ?? '', 'mailto:data@example.test'));
        $this->assertCount(1, Storage::disk('local')->directories('imports/snapshots/fzom'));

        $this->artisan('import:fzom')->assertSuccessful();

        $last = ImportRun::query()->where('source', 'fzom')->latest('id')->firstOrFail();
        $this->assertSame(ImportRunStatus::NotModified, $last->status);
        Http::assertSent(fn (Request $request): bool => ($request->header('If-None-Match')[0] ?? null) === '"pzz-v1"'
            && ($request->header('If-Modified-Since')[0] ?? null) === 'Tue, 06 Oct 2026 15:00:00 GMT');
    }

    public function test_snapshots_are_private_and_only_the_newest_are_kept(): void
    {
        config(['import.snapshot_retention' => 2]);
        $this->fakeRegistry();

        foreach ([1, 2, 3] as $_) {
            $this->artisan('import:fzom', ['--force' => true])->assertSuccessful();
            $this->travel(2)->seconds();
        }

        $this->assertCount(2, Storage::disk('local')->directories('imports/snapshots/fzom'));
        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_robots_txt_is_honoured(): void
    {
        $this->fakeRegistry(robots: "User-agent: *\nDisallow: /XML/\n");

        $this->artisan('import:fzom')->assertFailed();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'pzz.xml'));
    }

    public function test_robots_txt_wildcards_are_honoured(): void
    {
        $this->fakeRegistry(robots: "User-agent: *\nDisallow: /*.xml$\n");

        $this->artisan('import:fzom')->assertFailed();

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '.xml'));
    }

    public function test_a_robots_txt_server_error_means_not_fetching(): void
    {
        Http::fake(fn (Request $request) => str_ends_with($request->url(), '/robots.txt')
            ? Http::response('', 503)
            : Http::response((string) file_get_contents(base_path('tests/Fixtures/import/fzom/pzz.xml'))));

        $this->artisan('import:fzom')->assertFailed();

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '.xml'));
    }

    public function test_only_https_urls_on_the_configured_hosts_are_fetched_and_the_contact_is_sent_once(): void
    {
        config(['import.contact' => 'mailto:data@example.test']);
        $this->fakeRegistry();
        $this->artisan('import:fzom')->assertSuccessful();
        Http::assertSent(fn (Request $request): bool => ($request->header('User-Agent')[0] ?? '') === 'Zdravje360-DirectoryImport/1.0 (+mailto:data@example.test)');

        config(['import.fzom.files' => ['pzz' => 'http://169.254.169.254/XML/pzz.xml', 'spec' => 'https://registry.test/XML/spec.xml']]);
        $this->artisan('import:fzom', ['--force' => true])->assertFailed();
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '169.254'));
    }

    public function test_stored_snapshots_never_hold_excluded_fields_or_pharmacy_rows(): void
    {
        $this->fakeRegistry();

        $this->artisan('import:fzom')->assertSuccessful();

        $files = Storage::disk('local')->allFiles('imports/snapshots/fzom');
        $this->assertNotEmpty($files);
        $stored = implode("\n", array_map(fn (string $file): string => (string) Storage::disk('local')->get($file), $files));

        foreach (['ClenNaTim', 'PricinaOtsustvo', 'StatusValidnostID', 'RedovnaZamena', 'VZamena', 'ArhivskiBroj',
            'СЕСТРА НЕВИДЛИВА', 'ТАЈНА ПРИЧИНА', 'ЗАМЕНА', 'ВРЕМЕНА', 'АПТЕКА'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $stored);
        }

        $this->assertStringContainsString('ПРИМЕРОВСКА', $stored);

        // The run keeps the original's fingerprint, not the minimised copy's.
        $run = ImportRun::query()->where('source', 'fzom')->latest('id')->firstOrFail();
        $original = (string) file_get_contents(base_path('tests/Fixtures/import/fzom/pzz.xml'));
        $this->assertSame(hash('sha256', $original), $run->source_meta['files']['pzz']['sha256']);
        $this->assertSame(strlen($original), $run->source_meta['files']['pzz']['bytes']);
        $this->assertTrue($run->source_meta['files']['pzz']['minimised']);

        // A 304 run reuses the minimised copy and still imports the same doctors.
        $doctors = Doctor::query()->count();
        $this->artisan('import:fzom', ['--force' => true])->assertSuccessful();
        $this->assertSame($doctors, Doctor::query()->count());
    }

    public function test_an_old_snapshot_with_unfiltered_fields_is_deleted_on_the_next_run(): void
    {
        Storage::disk('local')->put('imports/snapshots/fzom/20260101-000000-legacy/pzz.xml', (string) file_get_contents(base_path('tests/Fixtures/import/fzom/pzz.xml')));
        $this->fakeRegistry();

        $this->artisan('import:fzom')->assertSuccessful();

        $this->assertFalse(Storage::disk('local')->exists('imports/snapshots/fzom/20260101-000000-legacy/pzz.xml'));
        $this->assertCount(1, Storage::disk('local')->directories('imports/snapshots/fzom'));
    }

    public function test_snapshots_of_failed_runs_are_pruned_and_dry_runs_keep_none(): void
    {
        config(['import.snapshot_retention' => 2]);
        $this->fakeRegistry();
        $this->artisan('import:fzom')->assertSuccessful();

        // The specialist list shrinks to nothing: every later apply hits the safety stop.
        $this->specBody = '<?xml version="1.0" encoding="utf-8"?><Lekari></Lekari>';

        foreach ([1, 2, 3] as $_) {
            $this->travel(2)->seconds();
            $this->artisan('import:fzom', ['--force' => true])->assertFailed();
        }

        $this->assertCount(2, Storage::disk('local')->directories('imports/snapshots/fzom'));

        $before = Storage::disk('local')->directories('imports/snapshots/fzom');
        $this->travel(2)->seconds();
        $this->artisan('import:fzom', ['--force' => true, '--dry-run' => true]);
        $this->assertSame($before, Storage::disk('local')->directories('imports/snapshots/fzom'));
    }
}
