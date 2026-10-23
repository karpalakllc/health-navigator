<?php

namespace Tests\Feature\Licences;

use App\Enums\ImportRunStatus;
use App\Enums\UserKind;
use App\Mail\ImportAlertMail;
use App\Models\ImportRun;
use App\Models\KomoraLicence;
use App\Models\KomoraLicenceDownload;
use App\Models\User;
use App\Support\DataOps\ImportAlerter;
use App\Support\Import\Contracts\DoctorLicenceSink;
use App\Support\Licences\Contracts\LicenceCandidateSource;
use App\Support\Licences\KomoraLicenceFetcher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeDoctorLicenceSink;
use Tests\Support\FakeLicenceCandidateSource;
use Tests\Support\KomoraListPdf;
use Tests\TestCase;

/**
 * Downloading the list politely: robots.txt, User-Agent, conditional
 * requests, private storage with retention. No network: Http::fake.
 */
class KomoraLicenceFetcherTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = 'https://lkm.example/mk/record/121/962/lista';

    private const FILE_A = 'https://lkm.example/upload/records/962/20260706_105959_%D0%90-%D0%92%2002.07.2026.pdf';

    private const FILE_B = 'https://lkm.example/upload/records/962/20260706_110050_%D0%93-%D0%96%2002.07.2026.pdf';

    /** @var list<Request> */
    private array $requests = [];

    private string $robots = "User-agent: *\nDisallow:\n";

    private int $robotsStatus = 200;

    private string $pdf;

    private string $etag = '"v1"';

    private string $page = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'licences.komora.list_url' => self::PAGE,
            'licences.komora.request_delay_ms' => 0,
            'import.user_agent' => 'Zdravje360-DirectoryImport/1.0',
            'import.contact' => 'mailto:test@example.invalid',
        ]);

        $this->pdf = KomoraListPdf::make([[
            ['name' => 'АНА ТЕСТОВСКА', 'specialty' => 'педијатрија', 'date' => '01.02.2030', 'number' => '0000001'],
        ]]);

        $this->page = '<div><p>Листата содржи активни лиценци изготвени заклучно со 2.7.2026 година.</p>'
            .'<a href="/upload/documents/Other.pdf">Друго</a>'
            .'<a href="'.str_replace('https://lkm.example', '', self::FILE_A).'">Список А-В</a>'
            .'<a href="'.self::FILE_B.'"> Список Г-Ж </a>'
            // Never followed: another host, an IP address, plain http.
            .'<a href="https://evil.example/upload/records/962/x.pdf">Список Х</a>'
            .'<a href="http://169.254.169.254/upload/records/962/y.pdf">Список У</a>'
            .'<a href="http://lkm.example/upload/records/962/z.pdf">Список З</a></div>';

        Http::fake(function (Request $request) {
            $this->requests[] = $request;

            return match (true) {
                str_ends_with($request->url(), '/robots.txt') => Http::response($this->robots, $this->robotsStatus),
                $request->url() === self::PAGE => Http::response($this->page),
                $request->hasHeader('If-None-Match', $this->etag) => Http::response('', 304),
                default => Http::response($this->pdf, 200, ['ETag' => $this->etag, 'Last-Modified' => 'Mon, 06 Jul 2026 10:59:59 GMT']),
            };
        });
    }

    public function test_it_reads_the_page_downloads_each_list_file_privately_and_identifies_itself(): void
    {
        $result = app(KomoraLicenceFetcher::class)->fetch();

        $this->assertTrue($result['changed']);
        $this->assertSame('2026-07-02', $result['list_date']?->toDateString());
        $this->assertSame(['А-В', 'Г-Ж'], array_column($result['files'], 'label'));

        foreach ($result['files'] as $file) {
            $this->assertSame($this->pdf, file_get_contents($file['path']));
            $this->assertStringContainsString('imports/komora/', $file['path']);
        }

        // robots.txt first, then the page, then the two files — not the unrelated PDF.
        $this->assertSame(
            ['https://lkm.example/robots.txt', self::PAGE, self::FILE_A, self::FILE_B],
            array_map(fn (Request $request): string => $request->url(), $this->requests),
        );

        foreach ($this->requests as $request) {
            $this->assertSame('Zdravje360-DirectoryImport/1.0 (+mailto:test@example.invalid)', $request->header('User-Agent')[0]);
        }

        $this->assertSame(2, KomoraLicenceDownload::query()->where('status', 'downloaded')->count());
        $this->assertSame(hash('sha256', $this->pdf), KomoraLicenceDownload::query()->value('sha256'));
    }

    public function test_an_unchanged_list_is_asked_for_conditionally_and_not_downloaded_again(): void
    {
        app(KomoraLicenceFetcher::class)->fetch();
        $this->requests = [];

        $second = app(KomoraLicenceFetcher::class)->fetch();

        $this->assertFalse($second['changed']);
        $this->assertCount(2, $second['files']);
        $fileRequests = array_values(array_filter($this->requests, fn (Request $request): bool => str_ends_with($request->url(), '.pdf')));
        $this->assertCount(2, $fileRequests);

        foreach ($fileRequests as $request) {
            $this->assertSame('"v1"', $request->header('If-None-Match')[0]);
            $this->assertSame('Mon, 06 Jul 2026 10:59:59 GMT', $request->header('If-Modified-Since')[0]);
        }

        $this->assertSame(2, KomoraLicenceDownload::query()->where('status', 'not_modified')->count());

        // --force downloads unconditionally.
        $this->requests = [];
        $forced = app(KomoraLicenceFetcher::class)->fetch(force: true);
        $this->assertTrue($forced['changed']);

        foreach ($this->requests as $request) {
            $this->assertFalse($request->hasHeader('If-None-Match'));
        }
    }

    public function test_only_the_last_few_runs_keep_their_raw_files(): void
    {
        config(['licences.komora.keep_snapshots' => 2]);

        foreach (range(1, 4) as $run) {
            app(KomoraLicenceFetcher::class)->fetch(force: true);
            $this->travel(1)->minutes();
        }

        $batches = KomoraLicenceDownload::query()->orderBy('id')->pluck('batch')->unique()->values();
        $this->assertCount(4, $batches);

        $kept = KomoraLicenceDownload::query()->whereNotNull('storage_path')->pluck('batch')->unique()->values()->all();
        $this->assertSame($batches->slice(2)->values()->all(), $kept);
        $this->assertCount(2, Storage::disk('local')->directories('imports/komora'));
        $this->assertCount(4, Storage::disk('local')->allFiles('imports/komora'));
    }

    public function test_a_robots_txt_that_disallows_the_list_stops_the_run_before_the_page_is_fetched(): void
    {
        $this->robots = "User-agent: *\nDisallow: /mk/record/";

        try {
            app(KomoraLicenceFetcher::class)->fetch();
            $this->fail('The fetch should have been refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('robots.txt', $exception->getMessage());
        }

        $this->assertSame(['https://lkm.example/robots.txt'], array_map(fn (Request $request): string => $request->url(), $this->requests));
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_the_command_skips_an_unchanged_list(): void
    {
        $this->app->instance(DoctorLicenceSink::class, new FakeDoctorLicenceSink);
        $this->app->instance(LicenceCandidateSource::class, new FakeLicenceCandidateSource);

        $this->artisan('import:komora-licences')
            ->expectsOutputToContain('List of 02.07.2026, 2 file(s).')
            ->assertSuccessful();

        $this->artisan('import:komora-licences')
            ->expectsOutputToContain('has not changed')
            ->assertSuccessful();
    }

    public function test_a_dry_run_or_a_failed_apply_does_not_use_up_a_new_list(): void
    {
        $this->app->instance(LicenceCandidateSource::class, new FakeLicenceCandidateSource);
        $this->app->instance(DoctorLicenceSink::class, new FakeDoctorLicenceSink);

        $this->artisan('import:komora-licences', ['--dry-run' => true])->assertSuccessful();

        // The apply after the dry run still processes the list.
        $this->artisan('import:komora-licences')
            ->expectsOutputToContain('List of 02.07.2026, 2 file(s).')
            ->assertSuccessful();

        // A new list version whose apply fails is processed again on the next run.
        $this->pdf = KomoraListPdf::make([[
            ['name' => 'АНА ТЕСТОВСКА', 'specialty' => 'педијатрија', 'date' => '01.02.2031', 'number' => '0000001'],
        ]]);
        $this->etag = '"v2"';
        $this->app->instance(LicenceCandidateSource::class, new class implements LicenceCandidateSource
        {
            public function doctorIdForLicence(string $licenceNumber): ?int
            {
                throw new RuntimeException('Database went away.');
            }

            public function candidatesFor(string $fullName): array
            {
                throw new RuntimeException('Database went away.');
            }
        });
        $this->artisan('import:komora-licences', ['--force' => false])->assertFailed();

        $this->app->instance(LicenceCandidateSource::class, new FakeLicenceCandidateSource);
        $this->artisan('import:komora-licences')
            ->expectsOutputToContain('List of 02.07.2026, 2 file(s).')
            ->assertSuccessful();

        // Now it is consumed.
        $this->artisan('import:komora-licences')->expectsOutputToContain('has not changed')->assertSuccessful();
    }

    public function test_a_robots_txt_server_error_stops_the_run(): void
    {
        $this->robotsStatus = 503;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('robots.txt');

        app(KomoraLicenceFetcher::class)->fetch();
    }

    /** A page that states no list date, linking to files whose names carry none either. */
    private function undatedPage(): void
    {
        $this->page = '<div><a href="/upload/records/962/a.pdf">Список А-В</a><a href="/upload/records/962/b.pdf">Список Г-Ж</a></div>';
    }

    public function test_a_downloaded_list_without_a_stated_date_needs_the_list_date_option(): void
    {
        $this->app->instance(DoctorLicenceSink::class, new FakeDoctorLicenceSink);
        $this->app->instance(LicenceCandidateSource::class, new FakeLicenceCandidateSource);
        $this->undatedPage();

        $this->artisan('import:komora-licences')
            ->expectsOutputToContain('--list-date=dd.mm.yyyy')
            ->assertFailed();
        $this->assertSame(ImportRunStatus::Failed, ImportRun::query()->where('source', 'komora')->latest('id')->first()?->status);
        $this->assertSame(0, KomoraLicence::query()->count(), 'Nothing is imported under a guessed date.');

        $this->artisan('import:komora-licences', ['--list-date' => '02.07.2026'])
            ->expectsOutputToContain('List of 02.07.2026, 2 file(s).')
            ->assertSuccessful();
        $this->assertSame('2026-07-02', ImportRun::query()->where('source', 'komora')->latest('id')->first()?->source_meta['list_date']);
        $this->assertSame('2026-07-02', KomoraLicence::query()->first()?->list_date->toDateString());
    }

    private function appliedList(string $date): void
    {
        ImportRun::query()->create([
            'source' => 'komora', 'dry_run' => false, 'status' => ImportRunStatus::Succeeded,
            'started_at' => now(), 'finished_at' => now(), 'source_meta' => ['list_date' => $date, 'complete_list' => true],
        ]);
    }

    private function staff(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = User::factory()->create(['user_kind' => UserKind::Staff]);
        $admin->syncRoles(['Administrator']);

        return $admin;
    }

    public function test_the_freshness_check_tells_staff_once_per_newer_list_and_never_imports(): void
    {
        Mail::fake();
        Cache::flush();
        config(['data_ops.alerts.email' => 'imports@example.invalid']);
        $admin = $this->staff();
        $member = User::factory()->create();
        $this->appliedList('2026-03-01');

        $this->artisan('import:komora-check')->expectsOutputToContain('licence list of 02.07.2026')->assertSuccessful();

        // Only the page was read: no list file, nothing imported.
        $this->assertSame(['https://lkm.example/robots.txt', self::PAGE], array_map(fn (Request $request): string => $request->url(), $this->requests));
        $this->assertSame(0, KomoraLicenceDownload::query()->count());
        $this->assertSame(0, KomoraLicence::query()->count());
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString('02.07.2026', (string) json_encode($admin->notifications()->first()?->data));
        $this->assertStringContainsString('01.03.2026', (string) json_encode($admin->notifications()->first()?->data));
        $this->assertSame(0, $member->notifications()->count());
        Mail::assertSent(ImportAlertMail::class, fn (ImportAlertMail $mail): bool => $mail->kind === ImportAlerter::KIND_NEW_LIST && str_contains((string) $mail->note, '02.07.2026'));

        // The next checks: the same list is not announced again (the mail throttle aside).
        Cache::flush();
        $this->artisan('import:komora-check')->expectsOutputToContain('already told')->assertSuccessful();
        $this->artisan('import:komora-check')->assertSuccessful();
        $this->assertSame(1, $admin->notifications()->count());
        Mail::assertSentCount(1);

        // A still newer list is announced once more.
        Cache::flush();
        $this->page = str_replace('2.7.2026', '15.10.2026', $this->page);
        $this->artisan('import:komora-check')->expectsOutputToContain('15.10.2026')->assertSuccessful();
        $this->assertSame(2, $admin->notifications()->count());
        Mail::assertSentCount(2);

        // Imported: nothing more to say.
        $this->appliedList('2026-10-15');
        $this->artisan('import:komora-check')->expectsOutputToContain('No newer licence list')->assertSuccessful();
        $this->assertSame(2, $admin->notifications()->count());
        $this->assertSame(5, ImportRun::query()->where('source', 'komora-check')->count());
    }

    public function test_the_freshness_check_is_quiet_when_the_page_shows_the_imported_list(): void
    {
        Mail::fake();
        $admin = $this->staff();
        $this->appliedList('2026-07-02');

        $this->artisan('import:komora-check')->expectsOutputToContain('No newer licence list')->assertSuccessful();

        $this->assertSame(0, $admin->notifications()->count());
        Mail::assertNothingSent();
        $this->assertSame(ImportRunStatus::NotModified, ImportRun::query()->where('source', 'komora-check')->first()?->status);
    }

    public function test_without_a_page_date_the_freshness_check_asks_for_each_file_conditionally(): void
    {
        $admin = $this->staff();
        $this->undatedPage();
        app(KomoraLicenceFetcher::class)->fetch();
        $this->appliedList('2026-07-02');
        $this->requests = [];

        $this->artisan('import:komora-check')->expectsOutputToContain('No newer licence list')->assertSuccessful();

        $heads = array_values(array_filter($this->requests, fn (Request $request): bool => $request->method() === 'HEAD'));
        $this->assertCount(2, $heads);
        $this->assertSame('"v1"', $heads[0]->header('If-None-Match')[0]);
        $this->assertSame(0, $admin->notifications()->count());

        // A file changed on the server.
        $this->etag = '"v2"';
        $this->artisan('import:komora-check')->expectsOutputToContain('list files changed')->assertSuccessful();
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString('--list-date', (string) json_encode($admin->notifications()->first()?->data));
        $this->assertSame(0, KomoraLicence::query()->count());
    }

    public function test_the_freshness_check_honours_robots_txt(): void
    {
        $this->robots = "User-agent: *\nDisallow: /mk/record/";

        $this->artisan('import:komora-check')->assertFailed();

        $this->assertSame(['https://lkm.example/robots.txt'], array_map(fn (Request $request): string => $request->url(), $this->requests));
        $this->assertSame(ImportRunStatus::Failed, ImportRun::query()->where('source', 'komora-check')->first()?->status);
    }
}
