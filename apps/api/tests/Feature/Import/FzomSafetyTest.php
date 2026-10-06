<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ImportRunStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\Specialty;
use App\Models\SpecialtyAlias;
use App\Support\Import\Fzom\FzomImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The ФЗОМ safety stop, partial snapshots and what "missing" means, against
 * generated synthetic snapshots (every name is invented).
 */
class FzomSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['import.disk' => 'local', 'import.max_missing_ratio' => 0.2, 'import.missing_after_runs' => 2]);
    }

    /**
     * A specialist snapshot with one doctor per number, all at one hospital.
     *
     * @param  list<int>  $numbers
     */
    public static function snapshot(array $numbers, string $specialty = 'ИНТЕРНА МЕДИЦИНА'): string
    {
        $rows = '';

        foreach ($numbers as $n) {
            $rows .= sprintf(
                '<Lekar><TipDogovor>Болничка</TipDogovor><TipDogovorID>16</TipDogovorID><DanocenBroj>4000000000777</DanocenBroj>'
                .'<ShifraZU>9000777</ShifraZU><ZdravstvenaUstanova>ЈЗУ ОПШТА БОЛНИЦА ПРОБНО</ZdravstvenaUstanova><RabotnaEdinica>ОДДЕЛ</RabotnaEdinica>'
                .'<Dejnost>%1$s</Dejnost><Specijalnosti>%1$s</Specijalnosti><Adresa>УЛ. ПРОБНА 1</Adresa><Mesto>ПРОБНО</Mesto>'
                .'<Faksimil>%2$d</Faksimil><Ime>ИМЕ</Ime><Prezime>%3$s</Prezime><ValidenOd>2025-01-01T00:00:00</ValidenOd></Lekar>',
                $specialty,
                800000 + $n,
                self::surname($n),
            );
        }

        $path = tempnam(sys_get_temp_dir(), 'fzom').'.xml';
        file_put_contents($path, '<?xml version="1.0" encoding="utf-8"?><Lekari>'.$rows.'</Lekari>');

        return $path;
    }

    /** ПРОБАА, ПРОБАБ, … — invented, unique per number. */
    public static function surname(int $n): string
    {
        $letters = ['А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ж', 'З', 'И', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф'];

        return 'ПРОБА'.$letters[intdiv($n, 20) % 20].$letters[$n % 20];
    }

    private function emptyPzz(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fzom').'.xml';
        file_put_contents($path, '<?xml version="1.0" encoding="utf-8"?><Lekari></Lekari>');

        return $path;
    }

    /**
     * @param  list<int>  $numbers
     */
    private function runSnapshot(array $numbers, bool $dryRun = false): ImportRun
    {
        $this->travel(1)->minutes();

        return app(FzomImportJob::class)->run($dryRun, null, ['pzz' => $this->emptyPzz(), 'spec' => self::snapshot($numbers)]);
    }

    public function test_ordinary_turnover_never_trips_the_safety_stop(): void
    {
        // 20 doctors; every run 2 leave (10 %) and 2 new ones arrive.
        $present = range(0, 19);
        $next = 20;

        for ($i = 0; $i < 6; $i++) {
            $run = $this->runSnapshot($present);

            $this->assertSame(ImportRunStatus::Succeeded, $run->status, "Run {$i}: ".$run->error);
            $this->assertLessThanOrEqual(2, $run->count('doctors_absent_this_run'));

            $present = array_merge(array_slice($present, 2), [$next, $next + 1]);
            $next += 2;
        }
    }

    public function test_a_truncated_snapshot_still_trips_the_safety_stop(): void
    {
        $this->runSnapshot(range(0, 19));

        $run = $this->runSnapshot(range(0, 9));

        $this->assertSame(ImportRunStatus::Failed, $run->status);
        $this->assertStringContainsString('Safety stop', (string) $run->error);
        $this->assertSame(10, $run->count('doctors_absent_this_run'));
    }

    public function test_the_safety_stop_comes_before_any_write(): void
    {
        $this->runSnapshot(range(0, 19));
        $aliases = SpecialtyAlias::query()->count();
        $items = ImportReviewItem::query()->count();
        $specialties = Specialty::withTrashed()->count();

        // Half the doctors gone, and the rest carry a wording never seen before.
        $this->travel(1)->minutes();
        $run = app(FzomImportJob::class)->run(false, null, ['pzz' => $this->emptyPzz(), 'spec' => self::snapshot(range(0, 9), 'НЕПОЗНАТА НОВА СТРУКА')]);

        $this->assertSame(ImportRunStatus::Failed, $run->status);
        $this->assertSame($aliases, SpecialtyAlias::query()->count());
        $this->assertSame($items, ImportReviewItem::query()->count());
        $this->assertSame($specialties, Specialty::withTrashed()->count());
    }

    public function test_a_partial_run_never_marks_anything_missing(): void
    {
        $pzz = base_path('tests/Fixtures/import/fzom/pzz.xml');
        $spec = base_path('tests/Fixtures/import/fzom/spec.xml');
        app(FzomImportJob::class)->run(false, null, ['pzz' => $pzz, 'spec' => $spec]);

        foreach ([1, 2] as $_) {
            $this->travel(1)->minutes();
            $run = app(FzomImportJob::class)->run(false, null, ['pzz' => $pzz]);
            $this->assertSame(ImportRunStatus::Succeeded, $run->status, (string) $run->error);
            $this->assertFalse($run->source_meta['complete']);
        }

        $this->assertSame(0, Doctor::query()->where('import_missing_runs', '>', 0)->count());
        $this->assertSame(0, Facility::query()->where('import_missing_runs', '>', 0)->count());
        $this->assertSame(0, ImportReviewItem::query()->where('kind', ImportReviewKind::Missing)->count());
    }

    public function test_import_fzom_with_one_local_file_is_a_partial_run(): void
    {
        $pzz = base_path('tests/Fixtures/import/fzom/pzz.xml');
        $spec = base_path('tests/Fixtures/import/fzom/spec.xml');
        $this->artisan('import:fzom', ['--pzz' => $pzz, '--spec' => $spec])->assertSuccessful();

        $this->travel(1)->minutes();
        $this->artisan('import:fzom', ['--spec' => $spec])->assertSuccessful();
        $this->travel(1)->minutes();
        $this->artisan('import:fzom', ['--spec' => $spec])->assertSuccessful();

        $this->assertSame(0, Doctor::query()->where('import_missing_runs', '>', 0)->count());
    }
}
