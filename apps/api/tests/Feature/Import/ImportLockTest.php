<?php

namespace Tests\Feature\Import;

use App\Models\ImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * One run per source at a time: a manual run during a scheduled one (or
 * the other way round) stops at once, cleanly, without touching anything.
 */
class ImportLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['import.disk' => 'local']);
    }

    public function test_a_second_fzom_run_exits_cleanly_while_one_is_running(): void
    {
        $held = Cache::lock('import:fzom', 60);
        $this->assertTrue($held->get());

        $this->artisan('import:fzom', [
            '--pzz' => base_path('tests/Fixtures/import/fzom/pzz.xml'),
            '--spec' => base_path('tests/Fixtures/import/fzom/spec.xml'),
        ])->expectsOutputToContain('already running')->assertSuccessful();

        $this->assertSame(0, ImportRun::query()->count());

        $held->release();
        $this->artisan('import:fzom', [
            '--pzz' => base_path('tests/Fixtures/import/fzom/pzz.xml'),
            '--spec' => base_path('tests/Fixtures/import/fzom/spec.xml'),
        ])->assertSuccessful();
        $this->assertSame(1, ImportRun::query()->where('source', 'fzom')->count());
        $this->assertTrue(Cache::lock('import:fzom', 60)->get(), 'The lock is released after the run.');
    }

    public function test_a_second_komora_run_exits_cleanly_while_one_is_running(): void
    {
        $this->assertTrue(Cache::lock('import:komora', 60)->get());

        $this->artisan('import:komora-licences', ['--file' => [base_path('tests/Fixtures/import/fzom/pzz.xml')], '--list-date' => '01.09.2026'])
            ->expectsOutputToContain('already running')
            ->assertSuccessful();

        $this->assertSame(0, ImportRun::query()->count());
    }

    public function test_a_second_website_run_exits_cleanly_while_one_is_running(): void
    {
        $this->assertTrue(Cache::lock('import:website', 60)->get());
        $path = tempnam(sys_get_temp_dir(), 'inst').'.json';
        file_put_contents($path, '{"institutions": []}');

        $this->artisan('import:institutions-json', ['path' => $path])
            ->expectsOutputToContain('already running')
            ->assertSuccessful();

        $this->assertSame(0, ImportRun::query()->count());
    }
}
