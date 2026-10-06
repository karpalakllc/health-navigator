<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\ImportSuppression;
use App\Models\SourceRecord;
use App\Support\Import\Fzom\FzomImportJob;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Retention of the import bookkeeping (docs/data-inventory.md): rows about
 * a profile go with it; diff summaries, closed review items and lifted
 * suppressions go after import.retention_days.
 */
class ImportRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['import.disk' => 'local']);
    }

    private function import(): ImportRun
    {
        return app(FzomImportJob::class)->run(false, null, [
            'pzz' => base_path('tests/Fixtures/import/fzom/pzz.xml'),
            'spec' => base_path('tests/Fixtures/import/fzom/spec.xml'),
        ]);
    }

    public function test_a_hard_deleted_profile_takes_its_import_rows_with_it_but_stays_suppressed(): void
    {
        $this->import();
        $doctor = Doctor::query()->where('fzo_facsimile', '900010')->firstOrFail();
        $facility = Facility::query()->where('fzo_code', '9000010')->firstOrFail();
        $count = fn (string $type, int $id): int => SourceRecord::query()->where('subject_type', $type)->where('subject_id', $id)->count()
            + FieldProvenance::query()->where('subject_type', $type)->where('subject_id', $id)->count()
            + ImportReviewItem::query()->where('subject_type', $type)->where('subject_id', $id)->count();
        $this->assertGreaterThan(0, $count('doctor', $doctor->getKey()));

        $doctor->delete();
        $this->assertGreaterThan(0, $count('doctor', $doctor->getKey()), 'A soft delete keeps them (it can be undone).');

        $doctor->forceDelete();
        $facility->forceDelete();

        $this->assertSame(0, $count('doctor', $doctor->getKey()));
        $this->assertSame(0, $count('facility', $facility->getKey()));
        $this->assertTrue(ImportSuppression::query()->active()->where('doctor_id', $doctor->getKey())->exists());
    }

    public function test_old_diff_summaries_closed_items_and_lifted_suppressions_are_pruned(): void
    {
        config(['import.retention_days' => 365]);
        $old = $this->import();
        $this->assertNotNull($old->diff_path);
        $oldPath = (string) $old->diff_path;
        $closed = ImportReviewItem::query()->firstOrFail();
        $closed->resolve(ImportReviewStatus::Dismissed, 'dismissed', null);
        $open = ImportReviewItem::raise('fzom', ImportReviewKind::Unmatched, 'still-open', 'Отворено');
        $doctor = Doctor::factory()->create();
        $doctor->delete();
        ImportSuppression::query()->active()->where('doctor_id', $doctor->getKey())->firstOrFail()->lift(null);

        $this->travel(366)->days();
        $recent = ImportReviewItem::raise('fzom', ImportReviewKind::Unmatched, 'recent', 'Ново');
        $recent->resolve(ImportReviewStatus::Dismissed, 'dismissed', null);

        $this->artisan('import:prune')->assertSuccessful();

        Storage::disk('local')->assertMissing($oldPath);
        $this->assertNull($old->refresh()->diff_path);
        $this->assertNull($closed->fresh());
        $this->assertNotNull($open->fresh(), 'Open items are never pruned.');
        $this->assertNotNull($recent->fresh());
        $this->assertSame(0, ImportSuppression::query()->whereNotNull('lifted_at')->count());
    }

    public function test_the_prune_runs_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'import:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('15 5 * * *', $events->first()->expression);
    }
}
