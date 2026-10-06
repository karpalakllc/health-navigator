<?php

namespace Tests\Feature\Licences;

use App\Enums\ImportRunStatus;
use App\Events\ImportRunFinished;
use App\Models\Doctor;
use App\Models\ImportRun;
use App\Models\KomoraLicence;
use App\Models\LicenceSpecialtyMapping;
use App\Support\Import\Contracts\DoctorLicenceSink;
use App\Support\Import\Contracts\LicenceReviewReason;
use App\Support\Licences\Contracts\LicenceCandidateSource;
use App\Support\Licences\KomoraLicenceImporter;
use App\Support\Licences\LicenceParseResult;
use App\Support\Licences\ParsedLicenceRow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\Support\FakeDoctorLicenceSink;
use Tests\Support\FakeLicenceCandidateSource;
use Tests\Support\KomoraListPdf;
use Tests\TestCase;

/**
 * Applying a parsed list: sink calls, staging, missing licences, dry runs and
 * the artisan command. Invented names throughout.
 */
class KomoraLicenceImportTest extends TestCase
{
    use RefreshDatabase;

    private FakeDoctorLicenceSink $sink;

    private FakeLicenceCandidateSource $candidates;

    /** @var array<string, int> */
    private array $doctors = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00'));
        $this->sink = new FakeDoctorLicenceSink;
        $this->candidates = new FakeLicenceCandidateSource;

        // Real rows, so staging can point at them (doctor_id foreign key).
        foreach (['Ана Тестовска' => ['ПЕДИЈАТАР'], 'Горан Истоименовски' => ['ОПШТА МЕДИЦИНА'], 'Горан Истоименовски ' => ['ОПШТА МЕДИЦИНА']] as $name => $specialties) {
            $doctor = Doctor::factory()->create(['full_name' => trim($name)]);
            $this->doctors[$name] = $doctor->id;
            $this->candidates->add($doctor->id, $name, $specialties);
        }

        $this->app->instance(DoctorLicenceSink::class, $this->sink);
        $this->app->instance(LicenceCandidateSource::class, $this->candidates);
    }

    private function row(string $name, ?string $specialty, string $number, string $validUntil = '2031-01-01'): ParsedLicenceRow
    {
        return new ParsedLicenceRow($name, $specialty, CarbonImmutable::parse($validUntil), $number, 'А-В#p1');
    }

    /**
     * @param  list<ParsedLicenceRow>  $rows
     * @param  list<array{reference: string, reason: string}>  $failures
     * @return array<string, int>
     */
    private function import(array $rows, bool $dryRun = false, array $failures = [], bool $complete = true, string $listDate = '2026-07-02'): array
    {
        return (new KomoraLicenceImporter($this->candidates, $this->sink))
            ->import(new LicenceParseResult($rows, $failures), CarbonImmutable::parse($listDate), $dryRun, $complete);
    }

    /**
     * @return list<ParsedLicenceRow>
     */
    private function list(): array
    {
        return [
            $this->row('АНА ТЕСТОВСКА', 'педијатрија', '0000001'),
            $this->row('ГОРАН ИСТОИМЕНОВСКИ', 'доктор на медицина во ПЗЗ', '0000002'),
            $this->row('НЕПОЗНАТ НЕПОЗНАТОВСКИ', 'интерна медицина', '0000003', '2025-01-01'),
            $this->row('АНА ТЕСТОВСКА', 'сосема нова специјалност', '0000004'),
        ];
    }

    public function test_attachments_and_review_items_go_to_the_sink_and_staging_records_the_outcome(): void
    {
        $counts = $this->import($this->list());

        $this->assertSame(4, $counts['rows_parsed']);
        $this->assertSame(1, $counts['attached']);
        $this->assertSame(1, $counts['ambiguous']);
        $this->assertSame(1, $counts['no_match']);
        $this->assertSame(1, $counts['specialty_mismatch']);
        $this->assertSame(1, $counts['expired']);
        $this->assertSame(1, $counts['unmapped_specialties']);

        $ana = $this->doctors['Ана Тестовска'];
        $this->assertSame(['0000001' => $ana], $this->sink->holders);
        $record = $this->sink->attached[$ana];
        $this->assertSame('педијатрија', $record->specialty);
        $this->assertSame('2031-01-01', $record->validUntil?->toDateString());
        $this->assertSame('2026-07-02', $record->observedAt->toDateString());
        $this->assertSame('komora', $record->source);
        $this->assertSame('А-В#p1', $record->sourceReference);

        $this->assertSame(['0000002'], $this->sink->queued(LicenceReviewReason::Ambiguous));
        $this->assertSame(['0000003'], $this->sink->queued(LicenceReviewReason::NoMatch));
        $this->assertSame(['0000004'], $this->sink->queued(LicenceReviewReason::SpecialtyMismatch));
        $this->assertEqualsCanonicalizing(
            [$this->doctors['Горан Истоименовски'], $this->doctors['Горан Истоименовски ']],
            $this->sink->review['0000002']['candidates'],
        );

        $staged = KomoraLicence::query()->orderBy('licence_number')->get()->keyBy('licence_number');
        $this->assertSame(['attached', 'ambiguous', 'no_match', 'specialty_mismatch'], $staged->pluck('outcome')->values()->all());
        $this->assertSame($ana, $staged['0000001']->doctor_id);
        $this->assertNull($staged['0000002']->doctor_id);
        $this->assertTrue($staged['0000003']->isExpired());
        $this->assertSame('АНА ТЕСТОВСКА', $staged['0000001']->name_key);

        // New wording is added to the mapping, unmapped, for staff.
        $new = LicenceSpecialtyMapping::query()->unmapped()->sole();
        $this->assertSame('сосема нова специјалност', $new->source_text);
        $this->assertSame('komora', $new->source);
    }

    public function test_a_second_run_of_the_same_list_changes_nothing(): void
    {
        $this->import($this->list());
        $holders = $this->sink->holders;

        $counts = $this->import($this->list());

        $this->assertSame(0, $counts['attached']);
        $this->assertSame(1, $counts['already_attached']);
        $this->assertSame($holders, $this->sink->holders);
        $this->assertSame(4, KomoraLicence::query()->count());
        $this->assertSame(1, LicenceSpecialtyMapping::query()->unmapped()->count());
    }

    public function test_locked_and_conflicting_profiles_are_counted_as_such(): void
    {
        $this->sink->locked = [$this->doctors['Ана Тестовска']];

        $counts = $this->import([$this->row('АНА ТЕСТОВСКА', 'педијатрија', '0000001')]);

        $this->assertSame(1, $counts['locked']);
        $this->assertSame('locked', KomoraLicence::query()->sole()->outcome);
        $this->assertNull(KomoraLicence::query()->sole()->doctor_id);
    }

    public function test_a_licence_that_drops_off_a_complete_list_is_marked_missing_and_nothing_else_happens(): void
    {
        $this->import($this->list());
        $this->travel(1)->days();

        $counts = $this->import(array_slice($this->list(), 1));

        $this->assertSame(1, $counts['missing_from_list']);
        $missing = KomoraLicence::query()->where('licence_number', '0000001')->sole();
        $this->assertSame('2026-07-02', $missing->missing_since?->toDateString());
        // The profile keeps its licence; the sink was not asked to remove anything.
        $this->assertSame($this->doctors['Ана Тестовска'], $this->sink->holders['0000001']);

        // Back on the next list: no longer missing.
        $this->travel(1)->days();
        $this->import($this->list());
        $this->assertNull($missing->fresh()?->missing_since);
    }

    public function test_an_unattached_licence_off_two_complete_lists_is_deleted_and_an_attached_one_kept(): void
    {
        $this->import($this->list());
        $this->travel(1)->days();
        // 0000001 is attached to a profile, 0000002 (namesakes) is not; both leave the list.
        $second = $this->import(array_slice($this->list(), 2), listDate: '2026-11-02');
        $this->assertSame(2, $second['missing_from_list']);
        $this->assertSame(0, $second['pruned_off_list']);

        // The same list again (a forced re-run) prunes nothing.
        $this->travel(1)->days();
        $this->assertSame(0, $this->import(array_slice($this->list(), 2), listDate: '2026-11-02')['pruned_off_list']);

        $this->travel(1)->days();
        $third = $this->import(array_slice($this->list(), 2), listDate: '2027-03-02');

        $this->assertSame(1, $third['pruned_off_list']);
        $this->assertNull(KomoraLicence::query()->where('licence_number', '0000002')->first());
        $this->assertNotNull(KomoraLicence::query()->where('licence_number', '0000001')->first()?->doctor_id);
        $this->assertNotNull(KomoraLicence::query()->where('licence_number', '0000003')->first());

        // A partial list never prunes.
        $this->assertSame(0, $this->import(array_slice($this->list(), 2), complete: false, listDate: '2027-07-02')['pruned_off_list']);
    }

    public function test_a_partial_or_partly_unreadable_list_never_marks_licences_missing(): void
    {
        $this->import($this->list());
        $this->travel(1)->days();

        $partial = $this->import(array_slice($this->list(), 1), complete: false);
        $unreadable = $this->import(array_slice($this->list(), 1), failures: [['reference' => 'А-В#p3', 'reason' => 'invalid date']]);

        $this->assertSame(0, $partial['missing_from_list']);
        $this->assertSame(0, $unreadable['missing_from_list']);
        $this->assertSame(0, KomoraLicence::query()->whereNotNull('missing_since')->count());
    }

    public function test_a_dry_run_counts_and_writes_nothing(): void
    {
        $counts = (new KomoraLicenceImporter($this->candidates, null))
            ->import(new LicenceParseResult($this->list()), CarbonImmutable::parse('2026-07-02'), dryRun: true);

        $this->assertSame([1, 1, 1, 1, 1], [$counts['attached'], $counts['ambiguous'], $counts['no_match'], $counts['specialty_mismatch'], $counts['expired']]);
        $this->assertSame([], $this->sink->holders);
        $this->assertSame([], $this->sink->review);
        $this->assertSame(0, KomoraLicence::query()->count());
        $this->assertSame(0, LicenceSpecialtyMapping::query()->unmapped()->count());
    }

    public function test_the_command_imports_local_list_files_and_prints_counts_without_names(): void
    {
        $directory = storage_path('framework/testing/komora-'.uniqid());
        File::ensureDirectoryExists($directory);
        $path = $directory.'/А-В 02.07.2026.pdf';
        file_put_contents($path, KomoraListPdf::make([[
            ['name' => 'АНА ТЕСТОВСКА', 'specialty' => 'педијатрија', 'date' => '01.02.2030', 'number' => '0000001'],
            ['name' => 'ГОРАН ИСТОИМЕНОВСКИ', 'specialty' => ['доктор на медицина во', 'ПЗЗ'], 'date' => '15.03.2031', 'number' => '0000002'],
        ]]));

        try {
            $this->artisan('import:komora-licences', ['--file' => [$path]])
                ->expectsOutputToContain('List of 02.07.2026, 1 file(s).')
                ->doesntExpectOutputToContain('ТЕСТОВСКА')
                ->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $this->assertSame(['0000001' => $this->doctors['Ана Тестовска']], $this->sink->holders);
        $this->assertSame(['0000002'], $this->sink->queued(LicenceReviewReason::Ambiguous));
        // A --file run is partial unless it says otherwise.
        $this->assertSame(0, KomoraLicence::query()->whereNotNull('missing_since')->count());
    }

    public function test_without_a_bound_sink_only_a_dry_run_works(): void
    {
        $this->app->forgetInstance(DoctorLicenceSink::class);
        $this->app->offsetUnset(DoctorLicenceSink::class);

        $directory = storage_path('framework/testing/komora-'.uniqid());
        File::ensureDirectoryExists($directory);
        $path = $directory.'/list.pdf';
        file_put_contents($path, KomoraListPdf::make([[
            ['name' => 'АНА ТЕСТОВСКА', 'specialty' => 'педијатрија', 'date' => '01.02.2030', 'number' => '0000001'],
        ]]));

        try {
            $this->artisan('import:komora-licences', ['--file' => [$path], '--list-date' => '02.07.2026'])
                ->expectsOutputToContain('No DoctorLicenceSink is bound')
                ->assertFailed();

            $this->artisan('import:komora-licences', ['--file' => [$path], '--list-date' => '02.07.2026', '--dry-run' => true])
                ->expectsOutputToContain('Dry run')
                ->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $this->assertSame(0, KomoraLicence::query()->count());
    }

    public function test_the_command_records_an_import_run_links_review_items_to_it_and_announces_it(): void
    {
        Event::fake([ImportRunFinished::class]);
        $directory = storage_path('framework/testing/komora-'.uniqid());
        File::ensureDirectoryExists($directory);
        $path = $directory.'/А-В 02.07.2026.pdf';
        file_put_contents($path, KomoraListPdf::make([[
            ['name' => 'АНА ТЕСТОВСКА', 'specialty' => 'педијатрија', 'date' => '01.02.2030', 'number' => '0000001'],
            ['name' => 'ГОРАН ИСТОИМЕНОВСКИ', 'specialty' => ['доктор на медицина во', 'ПЗЗ'], 'date' => '15.03.2031', 'number' => '0000002'],
        ]]));

        try {
            $this->artisan('import:komora-licences', ['--file' => [$path], '--dry-run' => true])->assertSuccessful();
            $dry = ImportRun::query()->latest('id')->firstOrFail();
            $this->assertTrue($dry->dry_run);
            $this->assertSame('komora', $dry->source);
            $this->assertSame(ImportRunStatus::Succeeded, $dry->status);
            Event::assertNotDispatched(ImportRunFinished::class);

            $this->artisan('import:komora-licences', ['--file' => [$path]])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertFalse($run->dry_run);
        $this->assertSame(ImportRunStatus::Succeeded, $run->status);
        $this->assertSame(1, $run->count('attached'));
        $this->assertSame('2026-07-02', $run->source_meta['list_date'] ?? null);

        // The run id travels with every record handed to the sink.
        $this->assertSame($run->id, $this->sink->attached[$this->doctors['Ана Тестовска']]->importRunId);
        $this->assertSame($run->id, $this->sink->review['0000002']['record']->importRunId);

        Event::assertDispatched(ImportRunFinished::class, fn (ImportRunFinished $event): bool => $event->source === 'komora'
            && $event->succeeded
            && $event->runId === $run->id
            && $event->seen === 2
            && $event->created === 1
            && $event->unmatched === 1);
    }

    public function test_a_failed_command_run_is_recorded_and_announced(): void
    {
        Event::fake([ImportRunFinished::class]);
        $directory = storage_path('framework/testing/komora-'.uniqid());
        File::ensureDirectoryExists($directory);
        $path = $directory.'/empty 02.07.2026.pdf';
        file_put_contents($path, KomoraListPdf::make([[]]));

        try {
            $this->artisan('import:komora-licences', ['--file' => [$path]])->assertFailed();
        } finally {
            File::deleteDirectory($directory);
        }

        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunStatus::Failed, $run->status);
        $this->assertStringContainsString('No licence rows', (string) $run->error);
        Event::assertDispatched(ImportRunFinished::class, fn (ImportRunFinished $event): bool => $event->source === 'komora' && ! $event->succeeded);
    }
}
