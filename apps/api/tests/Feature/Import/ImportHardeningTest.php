<?php

namespace Tests\Feature\Import;

use App\Enums\ImportRunStatus;
use App\Models\Activity;
use App\Models\Doctor;
use App\Models\ImportRun;
use App\Support\DataOps\ImportAlerter;
use App\Support\Import\Fzom\FzomImportJob;
use App\Support\Licences\KomoraLicenceListParser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Smaller import hardening from the wave 6 review.
 */
class ImportHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['import.disk' => 'local']);
    }

    public function test_licence_fields_never_reach_the_activity_log(): void
    {
        $doctor = Doctor::factory()->create();

        $doctor->forceFill([
            'licence_number' => '0012345',
            'licence_valid_until' => '2031-01-01',
            'licence_specialty_raw' => 'педијатрија',
            'licence_source' => 'komora',
            'licence_checked_at' => now(),
            'city' => 'Пробно',
        ])->save();

        $raw = (string) json_encode(Activity::query()->get()->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Пробно', $raw, 'Ordinary changes are still logged.');

        foreach (['0012345', 'licence_number', 'licence_valid_until', 'licence_specialty_raw', 'licence_checked_at'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $raw);
        }
    }

    public function test_a_database_error_is_stored_without_its_sql_and_values(): void
    {
        $run = ImportRun::start('fzom', false);
        $run->fail(new QueryException('sqlite', 'insert into doctors (full_name, licence_number) values (?, ?)', ['Тајно Име', '0012345'], new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: doctors.licence_number')));

        $this->assertStringNotContainsString('Тајно Име', (string) $run->error);
        $this->assertStringNotContainsString('0012345', (string) $run->error);
        $this->assertStringContainsString('QueryException', (string) $run->error);
        $this->assertStringContainsString('23000', (string) $run->error);
    }

    public function test_a_failed_alert_send_does_not_silence_the_next_one(): void
    {
        config(['data_ops.alerts.email' => 'alerts@example.test']);
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP down'));
        $this->assertFalse(app(ImportAlerter::class)->send('fzom', ImportAlerter::KIND_FAILED, error: 'x'));

        // The throttle is not used up: the next failure is mailed.
        $this->assertFalse(Cache::has('alerts:import:fzom:'.ImportAlerter::KIND_FAILED));
    }

    public function test_a_run_killed_midway_is_closed_by_the_daily_prune(): void
    {
        $killed = ImportRun::start('fzom', false);
        $this->travel(7)->hours();
        $running = ImportRun::start('komora', false);

        $this->artisan('import:prune')->assertSuccessful();

        $this->assertSame(ImportRunStatus::Failed, $killed->refresh()->status);
        $this->assertStringContainsString('interrupted', (string) $killed->error);
        $this->assertSame(ImportRunStatus::Running, $running->refresh()->status);
    }

    public function test_rows_without_a_facsimile_or_name_are_counted(): void
    {
        $spec = tempnam(sys_get_temp_dir(), 'fzom').'.xml';
        file_put_contents($spec, str_replace('<Faksimil>900012</Faksimil>', '', (string) file_get_contents(base_path('tests/Fixtures/import/fzom/spec.xml'))));

        $run = app(FzomImportJob::class)->run(true, null, ['pzz' => base_path('tests/Fixtures/import/fzom/pzz.xml'), 'spec' => $spec]);

        $this->assertSame(1, $run->count('rows_skipped_invalid'));
    }

    public function test_the_licence_pdf_parser_has_decode_limits(): void
    {
        $config = KomoraLicenceListParser::pdfConfig();

        $this->assertFalse($config->getRetainImageContent());
        $this->assertGreaterThan(0, $config->getDecodeMemoryLimit());
    }
}
