<?php

namespace Tests\Feature\Import;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Support\Import\RobotsTxt;
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

            return Http::response((string) file_get_contents(base_path("tests/Fixtures/import/fzom/{$file}.xml")), 200, [
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

        $last = ImportRun::query()->latest('id')->firstOrFail();
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

    public function test_robots_txt_groups(): void
    {
        $robots = "User-agent: Googlebot\nDisallow: /\n\nUser-agent: *\nDisallow: /private\nDisallow:\n";

        $this->assertSame(['/private'], RobotsTxt::disallowedFor($robots, 'Zdravje360-DirectoryImport/1.0'));
        $this->assertSame([], RobotsTxt::disallowedFor("User-agent: *\nDisallow:\n", 'Zdravje360-DirectoryImport/1.0'));
        $this->assertSame(['/'], RobotsTxt::disallowedFor("User-agent: zdravje360-directoryimport\nDisallow: /\n", 'Zdravje360-DirectoryImport/1.0'));
    }
}
