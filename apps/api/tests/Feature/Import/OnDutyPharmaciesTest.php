<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ImportRunStatus;
use App\Models\Facility;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\PharmacyDutyShift;
use App\Models\SiteSetting;
use App\Support\Import\Pharmacies\OnDutyScheduleParser;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * import:on-duty-pharmacies against a synthetic schedule laid out like ФЗОМ's
 * 2026 files (docs/urgent-care.md § On-duty pharmacies).
 */
class OnDutyPharmaciesTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = 'https://fzo.org.mk/dezurni-apteki';

    private const FILE = 'https://fzo.org.mk/sites/default/files/fzo/apteki/dezurni/261007-raspored-dezurni-apteki-10.2026.xlsx';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'import.disk' => 'local',
            'import.on_duty_pharmacies.request_delay_ms' => 0,
            'import.ca_bundle' => '',
        ]);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'duty').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }

    private function october(): string
    {
        return $this->xlsx([
            ['ОКТОМВРИ,2026'],
            ['Град/Населено место', '', 'Назив на ПЗУ аптека-организациона единица', 'Датум на спроведување на дежурство', 'Телефонски број за контакт', 'Начин на работа'],
            ['СКОПЈЕ'],
            [1, 'СКОПЈЕ-КАРПОШ', 'ЕУРОФАРМ - ТАФТАЛИЏЕ', '01.10-31.10.2026', '02/5 514-580', '24/7 работно време во аптека'],
            ['БИТОЛА'],
            [1, 'БИТОЛА', 'ПЗУ Аптека „Роса Вита“ Битола', '07.10.2026', '047/236-468', 'Од 23:00-07:00'],
            [2, 'БИТОЛА', 'ПЗУ Аптека Непозната', new DateTimeImmutable('2026-10-08'), 'Петар Петровски 070 111 222', 'Од 23:00-07:00'],
            ['ДЕБАР'],
            [1, 'ДЕБАР', 'ЗЕГИН / ZEGIN', '6.14.22.30.', '831-920,070-208-918', 'по телефонски повик од лекарски тим'],
            ['ВЕЛЕС'],
            [1, 'ВЕЛЕС', 'Магна Фарм 1', '7(недела)', '078 261 951', 'Димитар Влахов бр.30'],
            [2, 'ВЕЛЕС', 'Кутија', '2206-01-12', '078 000 000', '8:00 до 21:00'],
        ]);
    }

    /** What the fake ФЗОМ answers for the schedule file (changed between runs). */
    private ?\Closure $fileResponse = null;

    private function fakeSource(?string $file = null, int $fileStatus = 200, array $fileHeaders = ['ETag' => '"v1"']): void
    {
        $body = (string) file_get_contents($file ?? $this->october());
        $this->fileResponse = fn () => Http::response($fileStatus === 304 ? '' : $body, $fileStatus, $fileHeaders);

        if ($this->faked) {
            return;
        }

        $this->faked = true;
        Http::fake(function (Request $request) {
            return match (true) {
                $request->url() === 'https://fzo.org.mk/robots.txt' => Http::response("User-agent: *\nDisallow: /admin/\n"),
                $request->url() === self::PAGE => Http::response('<a href="/sites/default/files/fzo/apteki/dezurni/261007-raspored-dezurni-apteki-10.2026.xlsx">Распоред на дежурни аптеки за 2026 месец Октомври</a> <a href="https://evil.example/x-11.2026.xlsx">Распоред на дежурни аптеки за 2026 месец Ноември</a>'),
                $request->url() === self::FILE => ($this->fileResponse)(),
                default => Http::response('', 404),
            };
        });
    }

    private bool $faked = false;

    /**
     * @return array<string, array{0: mixed, 1: list<int>|null}>
     */
    public static function dates(): array
    {
        return [
            'one date' => ['07.10.2026', [7]],
            'dashes' => ['01-10-2026', [1]],
            'range' => ['01.10.2026-03.10.2026', [1, 2, 3]],
            'range with до' => ['01.10.2026 ДО 03.10.2026', [1, 2, 3]],
            'od do' => ['од 01.10.2026 до 02.10.2026', [1, 2]],
            'zero-d typo' => ['0д 05.10.2026 до 06.10.2026', [5, 6]],
            'short start' => ['01.10-03.10.2026', [1, 2, 3]],
            'short start до' => ['30.10. до 31.10.2026', [30, 31]],
            'from last month' => ['29.09. до 02.10.2026', [1, 2]],
            'em dash' => ['30.10—31.10.2026', [30, 31]],
            'two nights' => ['08/09-10-2026', [8, 9]],
            'comma list' => ['1,2,3,4', [1, 2, 3, 4]],
            'dot list' => ['6.14.22.30.', [6, 14, 22, 30]],
            'bare day' => ['7', [7]],
            'weekday note' => ['4(недела)', [4]],
            'year suffix' => ['од 01.10. до 04.10.2026 г.', [1, 2, 3, 4]],
            'space before year' => ['18.10. 2026', [18]],
            'missing dot' => ['05.10.2026-07.102026', [5, 6, 7]],
            'spreadsheet date' => [new DateTimeImmutable('2026-10-08'), [8]],
            'unformatted date cell' => [46303, [8]],
            'other month' => ['07.11.2026', null],
            'bad year' => [new DateTimeImmutable('2206-01-12'), null],
            'day 32' => ['32', null],
            'ambiguous two dots' => ['1.10.', null],
            'text' => ['по договор', null],
        ];
    }

    /**
     * @param  list<int>|null  $days
     */
    #[DataProvider('dates')]
    public function test_date_cells(mixed $value, ?array $days): void
    {
        $this->assertSame($days, OnDutyScheduleParser::parseDays($value, '2026-10'));
    }

    public function test_it_imports_the_month_links_pharmacies_and_queues_the_rest(): void
    {
        $karpos = Facility::factory()->pharmacy()->create(['name' => 'Еурофарм Тафталиџе', 'city' => 'Скопје - Карпош', 'phone' => '02 000 000']);
        $rosa = Facility::factory()->pharmacy()->create(['name' => 'Роса Вита', 'city' => 'Битола']);
        $this->fakeSource();

        $this->artisan('import:on-duty-pharmacies', ['--month' => '2026-10'])->assertSuccessful();

        $run = ImportRun::query()->where('source', 'on-duty-pharmacies')->latest('id')->firstOrFail();
        $this->assertSame(ImportRunStatus::Succeeded, $run->status, (string) $run->error);
        $this->assertSame(31 + 1 + 1 + 4 + 1, PharmacyDutyShift::query()->count());
        $this->assertSame(31, PharmacyDutyShift::query()->where('facility_id', $karpos->getKey())->count());

        $bitola = PharmacyDutyShift::query()->where('facility_id', $rosa->getKey())->sole();
        $this->assertSame('2026-10-07', $bitola->duty_date->toDateString());
        $this->assertSame(PharmacyDutyShift::MODE_HOURS, $bitola->mode);
        $this->assertSame('Од 23:00-07:00', $bitola->hours_text);

        // The pharmacist's name in the phone column is not kept.
        $unknown = PharmacyDutyShift::query()->where('pharmacy_name', 'ПЗУ Аптека Непозната')->sole();
        $this->assertNull($unknown->facility_id);
        $this->assertSame('070 111 222', $unknown->phone);
        $this->assertStringNotContainsString('Петар', (string) json_encode(PharmacyDutyShift::query()->get()));
        $this->assertStringNotContainsString('Петар', (string) json_encode(ImportReviewItem::query()->get()));

        // The Latin twin is dropped; a street in the hours column is an address.
        $this->assertSame([6, 14, 22, 30], PharmacyDutyShift::query()->where('pharmacy_name', 'ЗЕГИН')->orderBy('duty_date')->get()->map(fn ($s) => $s->duty_date->day)->all());
        $veles = PharmacyDutyShift::query()->where('town', 'Велес')->sole();
        $this->assertSame('Димитар Влахов бр.30', $veles->address);
        $this->assertNull($veles->hours_text);

        // Unmatched pharmacies in a town the directory covers and the unreadable
        // row go to the review queue; nothing is created. Дебар and Велес have
        // no published pharmacy yet: their rows are in the run report only.
        $this->assertSame(2, Facility::query()->pharmacy()->count());
        $items = ImportReviewItem::query()->where('source', 'on-duty-pharmacies')->where('kind', ImportReviewKind::Unmatched)->get();
        $this->assertSame(['ПЗУ Аптека Непозната'], $items->where('details.reason', 'pharmacy_not_in_directory')->pluck('details.name')->sort()->values()->all());
        $this->assertSame(1, $items->where('details.reason', 'unreadable_date')->count());
        $this->assertSame(1, $run->count('rows_unreadable'));

        // Only https files on the page's own host: the foreign November link was ignored.
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'evil.example'));
        Http::assertSent(fn (Request $request) => str_contains($request->header('User-Agent')[0] ?? '', 'mailto:'));
        $this->assertCount(1, Storage::disk('local')->files('imports/on-duty-pharmacies'));
    }

    public function test_while_a_town_has_no_published_pharmacy_its_unmatched_ones_are_reported_not_queued(): void
    {
        // Битола: one published pharmacy. Дебар: a draft only. Велес, Скопје: none.
        Facility::factory()->pharmacy()->create(['name' => 'Роса Вита', 'city' => 'Битола']);
        Facility::factory()->pharmacy()->unpublished()->create(['name' => 'Друга', 'city' => 'Дебар']);
        $this->fakeSource();

        $this->artisan('import:on-duty-pharmacies', ['--month' => '2026-10'])->assertSuccessful();

        $run = ImportRun::query()->where('source', 'on-duty-pharmacies')->latest('id')->firstOrFail();
        $queued = ImportReviewItem::query()->where('source', 'on-duty-pharmacies')->get()
            ->where('details.reason', 'pharmacy_not_in_directory')->pluck('details.name')->values()->all();
        $this->assertSame(['ПЗУ Аптека Непозната'], $queued);
        $this->assertSame(4, $run->count('pharmacies_unmatched'));
        $this->assertSame(3, $run->count('pharmacies_unmatched_not_queued'));

        // The run report lists every unmatched pharmacy, queued or not.
        $this->assertNotNull($run->diff_path);
        $report = (string) Storage::disk('local')->get($run->diff_path);
        foreach (['ЕУРОФАРМ - ТАФТАЛИЏЕ, Скопје', 'ЗЕГИН, Дебар', 'Магна Фарм 1, Велес'] as $label) {
            $this->assertStringContainsString($label, $report);
        }
        $this->assertStringContainsString('unmatched', $report);

        // The rows still show under ФЗОМ's name.
        $this->assertSame(4, PharmacyDutyShift::query()->where('town', 'Дебар')->whereNull('facility_id')->count());
    }

    public function test_a_second_run_is_a_conditional_get_and_a_changed_file_replaces_the_month(): void
    {
        $this->fakeSource();
        $this->artisan('import:on-duty-pharmacies', ['--month' => '2026-10'])->assertSuccessful();
        $first = PharmacyDutyShift::query()->count();

        $this->fakeSource(fileStatus: 304);
        $this->artisan('import:on-duty-pharmacies', ['--month' => '2026-10'])->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->url() === self::FILE && $request->hasHeader('If-None-Match', '"v1"'));
        $this->assertSame(ImportRunStatus::NotModified, ImportRun::query()->latest('id')->firstOrFail()->status);
        $this->assertSame($first, PharmacyDutyShift::query()->count());

        PharmacyDutyShift::query()->create([
            'month' => '2025-09', 'duty_date' => '2025-09-01', 'town' => 'Битола', 'town_key' => 'БИТОЛА',
            'pharmacy_name' => 'Стара', 'name_key' => 'СТАРА', 'mode' => 'hours',
        ]);
        $this->fakeSource($this->xlsx([
            ['ОКТОМВРИ,2026'],
            [1, 'БИТОЛА', 'Нова', '09.10.2026', '047 000 000', 'Од 23:00-07:00'],
        ]), 200, ['ETag' => '"v2"']);
        $this->artisan('import:on-duty-pharmacies', ['--month' => '2026-10'])->assertSuccessful();

        $this->assertSame(1, PharmacyDutyShift::query()->count(), 'October replaced; a month over a year old expired.');
    }

    public function test_a_dry_run_keeps_nothing_and_an_unpublished_month_is_not_an_error(): void
    {
        $this->fakeSource();

        $this->artisan('import:on-duty-pharmacies', ['--month' => '2026-10', '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, PharmacyDutyShift::query()->count());
        $this->assertSame(0, ImportReviewItem::query()->count());

        $this->artisan('import:on-duty-pharmacies', ['--month' => '2026-12'])->assertSuccessful();
        $run = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(ImportRunStatus::NotModified, $run->status);
        $this->assertSame('not_published', $run->source_meta['reason']);
    }

    public function test_robots_txt_stops_the_run(): void
    {
        Http::fake([
            'https://fzo.org.mk/robots.txt' => Http::response("User-agent: *\nDisallow: /dezurni-apteki\n"),
            '*' => Http::response('should not be fetched', 500),
        ]);
        $this->artisan('import:on-duty-pharmacies', ['--month' => '2026-10'])->assertFailed();
        Http::assertNotSent(fn (Request $request) => $request->url() === self::PAGE);
        $this->assertStringContainsString('robots.txt', (string) ImportRun::query()->latest('id')->firstOrFail()->error);
    }

    public function test_a_schedule_without_rows_replaces_nothing(): void
    {
        PharmacyDutyShift::query()->create([
            'month' => '2026-10', 'duty_date' => '2026-10-01', 'town' => 'Битола', 'town_key' => 'БИТОЛА',
            'pharmacy_name' => 'Стара', 'name_key' => 'СТАРА', 'mode' => 'hours',
        ]);
        $this->fakeSource($this->xlsx([['ОКТОМВРИ,2026'], ['Град', '', 'Назив']]));
        $this->artisan('import:on-duty-pharmacies', ['--month' => '2026-10'])->assertFailed();
        $this->assertSame(1, PharmacyDutyShift::query()->count(), 'A file with no rows replaces nothing.');
        $this->assertStringContainsString('No on-duty pharmacy could be read', (string) ImportRun::query()->latest('id')->firstOrFail()->error);
    }

    public function test_it_is_scheduled_on_the_1st_15th_and_28th_and_on_by_default(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_ends_with((string) $event->command, 'import:on-duty-pharmacies'))
            ->values();

        $this->assertCount(1, $events);
        $this->assertSame('40 6 1,15,28 * *', $events[0]->expression);
        $this->assertSame('Europe/Skopje', $events[0]->timezone);
        $this->assertTrue(config('data_ops.schedule.on-duty-pharmacies.enabled'));
        $this->assertTrue(config('import.on_duty_pharmacies.enabled'));
        $this->assertTrue($events[0]->filtersPass($this->app));
    }

    public function test_without_a_month_it_imports_this_month_and_skips_an_unlisted_next_one(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 06:40', 'Europe/Skopje'));
        $this->fakeSource();

        $this->artisan('import:on-duty-pharmacies')->assertSuccessful();

        $runs = ImportRun::query()->where('source', 'on-duty-pharmacies')->get();
        $this->assertCount(1, $runs, 'November is not listed (the only link is on another host): no run.');
        $this->assertSame('2026-10', $runs[0]->source_meta['month']);
    }

    public function test_the_switch_turns_the_import_off(): void
    {
        config(['import.on_duty_pharmacies.enabled' => false]);
        Http::fake();

        $this->artisan('import:on-duty-pharmacies')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, ImportRun::query()->count());
    }

    public function test_tonights_pharmacies_reach_the_finder_and_the_profile(): void
    {
        SiteSetting::current()->update(['public_pharmacies' => true]);
        $rosa = Facility::factory()->pharmacy()->create(['name' => 'Роса Вита', 'city' => 'Битола', 'address' => 'ул. Широк Сокак 1']);
        $this->fakeSource();
        $this->artisan('import:on-duty-pharmacies', ['--month' => '2026-10'])->assertSuccessful();

        // 2026-10-08 02:00 in Skopje: still the night of the 7th.
        $this->travelTo(CarbonImmutable::parse('2026-10-08 02:00', 'Europe/Skopje'));

        $this->getJson('/api/v1/urgent-care?'.http_build_query(['city' => 'Битола']))
            ->assertOk()
            ->assertJsonPath('meta.on_duty_pharmacies.available', true)
            ->assertJsonPath('meta.on_duty_pharmacies.date', '2026-10-07')
            ->assertJsonPath('meta.on_duty_pharmacies.items.0.name', 'Роса Вита')
            ->assertJsonPath('meta.on_duty_pharmacies.items.0.slug', $rosa->slug)
            ->assertJsonPath('meta.on_duty_pharmacies.items.0.hours_text', 'Од 23:00-07:00')
            ->assertJsonPath('meta.on_duty_pharmacies.items.0.address', 'ул. Широк Сокак 1')
            ->assertJsonCount(1, 'meta.on_duty_pharmacies.items');

        $this->getJson('/api/v1/urgent-care?'.http_build_query(['city' => 'Скопје']))
            ->assertJsonPath('meta.on_duty_pharmacies.items.0.name', 'Еурофарм - Тафталиџе')
            ->assertJsonPath('meta.on_duty_pharmacies.items.0.municipality', 'Карпош')
            ->assertJsonPath('meta.on_duty_pharmacies.items.0.slug', null)
            ->assertJsonPath('meta.on_duty_pharmacies.items.0.mode', 'all_day');

        // No city: availability only.
        $this->getJson('/api/v1/urgent-care')->assertJsonPath('meta.on_duty_pharmacies.items', null);

        $this->getJson('/api/v1/pharmacies/'.$rosa->slug)
            ->assertJsonPath('data.on_duty_today.date', '2026-10-07')
            ->assertJsonPath('data.on_duty_today.hours_text', 'Од 23:00-07:00');

        // Another month not imported: the placeholder.
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'Europe/Skopje'));
        $this->getJson('/api/v1/urgent-care?'.http_build_query(['city' => 'Битола']))
            ->assertJsonPath('meta.on_duty_pharmacies.available', false);
        $this->getJson('/api/v1/pharmacies/'.$rosa->slug)->assertJsonPath('data.on_duty_today', null);
    }
}
