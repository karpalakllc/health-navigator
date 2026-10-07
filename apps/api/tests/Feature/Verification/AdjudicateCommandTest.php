<?php

namespace Tests\Feature\Verification;

use App\Enums\ImportReviewKind;
use App\Enums\ImportRunStatus;
use App\Events\ImportRunFinished;
use App\Models\Doctor;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * `import:adjudicate` by hand, nightly and after every import, and the
 * owner's summary — counts only, never a name.
 */
class AdjudicateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_report_prints_counts_by_rule_reason_and_open_question_without_names_or_writes(): void
    {
        $doctor = Doctor::factory()->create(['full_name' => 'Измислен Тајновски']);
        ImportReviewItem::raise('verification', ImportReviewKind::Uncertain, 'specialty-pair:x', 'Licence specialty pair', ['reason' => 'specialty_mapping']);
        ImportReviewItem::raise('website', ImportReviewKind::Unmatched, 'specialty:website:ИВФ', 'Unmapped specialty ИВФ', ['raw' => 'ИВФ']);

        $this->assertSame(0, Artisan::call('import:adjudicate', ['--report' => true]));
        $output = Artisan::output();

        $this->assertStringContainsString('Not verified, by reason', $output);
        $this->assertStringContainsString('no_import_evidence', $output);
        $this->assertStringContainsString('specialty_mapping', $output);
        $this->assertStringContainsString('unmapped_specialty_wording', $output);
        $this->assertStringContainsString('Total open: 2', $output);
        $this->assertStringContainsString('in ФЗОМ without a licence, ready for „Објави ги и неверификуваните од ФЗОМ“: 0', $output);
        $this->assertStringNotContainsString('Тајновски', $output);
        $this->assertNull($doctor->fresh()->verification_checked_at, 'The report writes nothing.');
        $this->assertTrue(ImportRun::query()->where('source', 'verification')->sole()->dry_run);
    }

    public function test_an_apply_writes_and_prints_the_counts(): void
    {
        $doctor = Doctor::factory()->create();

        $this->artisan('import:adjudicate')->assertSuccessful()->expectsOutputToContain('doctors_unverified.no_import_evidence');

        $this->assertSame('no_import_evidence', $doctor->fresh()->verification_reasons['reason']);
    }

    public function test_it_runs_nightly_and_after_a_successful_import_only(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn (Event $event): bool => str_ends_with((string) $event->command, 'import:adjudicate'));
        $this->assertCount(1, $events);
        $this->assertSame('50 5 * * *', $events->first()->expression);

        ImportRunFinished::dispatch('komora', false, error: 'boom');
        $this->assertSame(0, ImportRun::query()->where('source', 'verification')->count());

        ImportRunFinished::dispatch('komora', true, seen: 10);
        $this->assertSame(1, ImportRun::query()->where('source', 'verification')->where('dry_run', false)->count());

        config(['import.verification.after_import' => false]);
        ImportRunFinished::dispatch('fzom', true, seen: 10);
        $this->assertSame(1, ImportRun::query()->where('source', 'verification')->count());
    }

    public function test_the_report_warns_when_the_fzom_register_is_too_old(): void
    {
        ImportRun::query()->create([
            'source' => 'fzom', 'dry_run' => false, 'status' => ImportRunStatus::Succeeded,
            'started_at' => now()->subDays(100), 'finished_at' => now()->subDays(100), 'source_meta' => ['complete' => true],
        ]);

        $this->assertSame(0, Artisan::call('import:adjudicate', ['--report' => true]));

        $this->assertStringContainsString('WARNING: the ФЗОМ register is older than 45 days', Artisan::output());
    }
}
