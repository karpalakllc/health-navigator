<?php

namespace Tests\Feature\Import;

use App\Enums\ImportRunStatus;
use App\Events\ImportRunFinished;
use App\Mail\ImportAlertMail;
use App\Models\ImportRun;
use App\Support\Import\Fzom\FzomImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The import core announces finished ФЗОМ runs as ImportRunFinished, the
 * event the data-ops alerts listen to (docs/data-import.md §10).
 */
class ImportRunAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['import.disk' => 'local']);
    }

    /**
     * @param  array<string, string>|null  $files
     */
    private function import(bool $dryRun = false, ?array $files = null): ImportRun
    {
        return app(FzomImportJob::class)->run($dryRun, null, $files ?? [
            'pzz' => base_path('tests/Fixtures/import/fzom/pzz.xml'),
            'spec' => base_path('tests/Fixtures/import/fzom/spec.xml'),
        ]);
    }

    public function test_an_apply_is_announced_with_its_counts_mapped(): void
    {
        Event::fake([ImportRunFinished::class]);

        $run = $this->import();

        $this->assertSame(ImportRunStatus::Succeeded, $run->status, (string) $run->error);
        Event::assertDispatchedTimes(ImportRunFinished::class, 1);
        Event::assertDispatched(ImportRunFinished::class, function (ImportRunFinished $event) use ($run): bool {
            return $event->source === 'fzom'
                && $event->succeeded
                && $event->runId === $run->getKey()
                && $event->seen === $run->count('doctors_in_source') + $run->count('facilities_in_source')
                && $event->created === 4 + 3 + 1
                && $event->updated === 0
                && $event->missing === 0
                && $event->conflicts === 0
                && $event->unmatched === 1
                && $event->error === null
                && str_contains((string) $event->reviewUrl, '/import-runs/'.$run->getKey());
        });
    }

    public function test_dry_runs_and_unchanged_reruns_are_quiet_where_they_should_be(): void
    {
        Event::fake([ImportRunFinished::class]);

        $this->import(dryRun: true);
        Event::assertNotDispatched(ImportRunFinished::class);

        $this->import();
        $second = $this->import();

        // An unchanged rerun is still a real run: announced, with nothing changed.
        Event::assertDispatched(ImportRunFinished::class, fn (ImportRunFinished $event): bool => $event->runId === $second->getKey() && $event->changed() === 0);
    }

    public function test_a_failed_apply_is_announced_and_mails_the_alert_inbox(): void
    {
        Mail::fake();
        config(['data_ops.alerts.email' => 'alerts@example.test']);

        $run = $this->import(files: [
            'pzz' => base_path('tests/Fixtures/import/fzom/does-not-exist.xml'),
            'spec' => base_path('tests/Fixtures/import/fzom/spec.xml'),
        ]);

        $this->assertSame(ImportRunStatus::Failed, $run->status);
        Mail::assertSent(ImportAlertMail::class, 1);
    }

    public function test_a_first_large_apply_mails_a_large_diff_alert(): void
    {
        Mail::fake();
        config(['data_ops.alerts.email' => 'alerts@example.test', 'data_ops.alerts.large_diff_min' => 5]);

        $this->import(dryRun: true);
        Mail::assertNothingSent();

        $this->import();
        Mail::assertSent(ImportAlertMail::class, 1);
    }
}
