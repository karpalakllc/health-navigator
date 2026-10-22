<?php

namespace Tests\Feature\Api\V1;

use App\Models\Facility;
use App\Models\PharmacyDutyShift;
use App\Support\UrgentCare\OnDutyPharmacies;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * „Дежурна денес“ in the pharmacy directory and the search vertical
 * (docs/urgent-care.md § On-duty pharmacies): on_duty_today on every card,
 * on-duty pharmacies first in a city, and the `on_duty=1` filter.
 */
class PharmacyOnDutyListTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacy(string $name, string $city = 'Битола', bool $featured = false): Facility
    {
        return Facility::factory()->pharmacy()->create([
            'name' => $name,
            'city' => $city,
            'is_featured' => $featured,
        ]);
    }

    private function shift(Facility $pharmacy, string $date, string $mode, ?string $hours = null): PharmacyDutyShift
    {
        return PharmacyDutyShift::query()->create([
            'month' => substr($date, 0, 7),
            'duty_date' => $date,
            'town' => $pharmacy->city ?? 'Битола',
            'town_key' => mb_strtolower($pharmacy->city ?? 'битола'),
            'pharmacy_name' => $pharmacy->name,
            'name_key' => mb_strtolower($pharmacy->name),
            'facility_id' => $pharmacy->getKey(),
            'mode' => $mode,
            'hours_text' => $hours,
        ]);
    }

    /**
     * @param  array<string, string>  $query
     * @return list<string>
     */
    private function names(array $query = []): array
    {
        return array_column($this->getJson('/api/v1/pharmacies?'.http_build_query($query))->assertOk()->json('data'), 'name');
    }

    public function test_the_duty_day_turns_at_seven_in_skopje_not_at_midnight_or_utc(): void
    {
        // 02:00 Skopje on the 8th: still the night of the 7th.
        $this->assertSame('2026-10-07', PharmacyDutyShift::currentDutyDate(CarbonImmutable::parse('2026-10-08 02:00', 'Europe/Skopje'))->toDateString());
        $this->assertSame('2026-10-07', PharmacyDutyShift::currentDutyDate(CarbonImmutable::parse('2026-10-08 06:59', 'Europe/Skopje'))->toDateString());
        $this->assertSame('2026-10-08', PharmacyDutyShift::currentDutyDate(CarbonImmutable::parse('2026-10-08 07:00', 'Europe/Skopje'))->toDateString());
        // 23:30 UTC on the 7th is 01:30 on the 8th in Skopje (CEST): the 7th's night.
        $this->assertSame('2026-10-07', PharmacyDutyShift::currentDutyDate(CarbonImmutable::parse('2026-10-07 23:30', 'UTC'))->toDateString());
        // 05:30 UTC on the 8th is 07:30 in Skopje: the 8th.
        $this->assertSame('2026-10-08', PharmacyDutyShift::currentDutyDate(CarbonImmutable::parse('2026-10-08 05:30', 'UTC'))->toDateString());
    }

    public function test_list_items_carry_on_duty_today_across_midnight(): void
    {
        $rosa = $this->pharmacy('Роса Вита');
        $this->pharmacy('Арника');
        $this->shift($rosa, '2026-10-07', PharmacyDutyShift::MODE_HOURS, 'Од 23:00-07:00');

        $this->travelTo(CarbonImmutable::parse('2026-10-08 02:00', 'Europe/Skopje'));

        $response = $this->getJson('/api/v1/pharmacies')->assertOk();
        $items = collect($response->json('data'))->keyBy('name');
        $this->assertSame(['date' => '2026-10-07', 'mode' => 'hours', 'hours_text' => 'Од 23:00-07:00'], $items['Роса Вита']['on_duty_today']);
        $this->assertNull($items['Арника']['on_duty_today']);

        // After 07:00 the 7th's night is over.
        $this->travelTo(CarbonImmutable::parse('2026-10-08 07:05', 'Europe/Skopje'));
        $this->getJson('/api/v1/pharmacies')->assertJsonPath('data.1.on_duty_today', null);
    }

    public function test_in_a_city_on_duty_pharmacies_come_first_24h_before_on_call(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00', 'Europe/Skopje'));

        $this->pharmacy('Арника', featured: true);
        $bella = $this->pharmacy('Бела');
        $vita = $this->pharmacy('Вита');
        $this->pharmacy('Галена');
        $elsewhere = $this->pharmacy('Делфина', 'Охрид');
        $this->shift($vita, '2026-10-08', PharmacyDutyShift::MODE_ON_CALL);
        $this->shift($bella, '2026-10-08', PharmacyDutyShift::MODE_ON_CALL);
        $galena = Facility::query()->where('name', 'Галена')->firstOrFail();
        $this->shift($galena, '2026-10-08', PharmacyDutyShift::MODE_ALL_DAY);
        $this->shift($elsewhere, '2026-10-08', PharmacyDutyShift::MODE_ALL_DAY);
        // Yesterday's shift no longer counts.
        $arnika = Facility::query()->where('name', 'Арника')->firstOrFail();
        $this->shift($arnika, '2026-10-07', PharmacyDutyShift::MODE_ALL_DAY);

        $this->assertSame(['Галена', 'Бела', 'Вита', 'Арника'], $this->names(['city' => 'Битола']));

        // Without a city the directory keeps its usual order.
        $this->assertSame(['Арника', 'Бела', 'Вита', 'Галена', 'Делфина'], $this->names());
    }

    public function test_the_on_duty_filter_keeps_only_tonights_pharmacies(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 03:00', 'Europe/Skopje'));

        $bella = $this->pharmacy('Бела');
        $vita = $this->pharmacy('Вита');
        $this->pharmacy('Арника');
        $ohrid = $this->pharmacy('Делфина', 'Охрид');
        $this->shift($bella, '2026-10-07', PharmacyDutyShift::MODE_ON_CALL);
        $this->shift($vita, '2026-10-07', PharmacyDutyShift::MODE_ALL_DAY);
        $this->shift($ohrid, '2026-10-07', PharmacyDutyShift::MODE_ALL_DAY);
        // Tomorrow's day shift is not tonight's.
        $arnika = Facility::query()->where('name', 'Арника')->firstOrFail();
        $this->shift($arnika, '2026-10-08', PharmacyDutyShift::MODE_ALL_DAY);

        $this->assertSame(['Вита', 'Бела'], $this->names(['city' => 'Битола', 'on_duty' => '1']));
        $this->assertSame(['Вита', 'Делфина', 'Бела'], $this->names(['on_duty' => '1']));
        $this->assertSame([], $this->names(['city' => 'Струга', 'on_duty' => '1']));

        $this->getJson('/api/v1/pharmacies?on_duty=maybe')->assertUnprocessable();
    }

    public function test_a_pharmacy_listed_twice_keeps_its_most_available_shift(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00', 'Europe/Skopje'));
        $bella = $this->pharmacy('Бела');
        $this->shift($bella, '2026-10-08', PharmacyDutyShift::MODE_ON_CALL);
        $this->shift($bella, '2026-10-08', PharmacyDutyShift::MODE_ALL_DAY);

        $this->getJson('/api/v1/pharmacies?'.http_build_query(['city' => 'Битола']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.on_duty_today.mode', 'all_day');
        $this->assertSame('all_day', OnDutyPharmacies::forPharmacy($bella)['mode'] ?? null);
    }

    public function test_the_search_vertical_carries_on_duty_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00', 'Europe/Skopje'));
        $bella = $this->pharmacy('Аптека Бела');
        $this->shift($bella, '2026-10-08', PharmacyDutyShift::MODE_ALL_DAY);

        $this->getJson('/api/v1/search?'.http_build_query(['q' => 'Бела']))
            ->assertOk()
            ->assertJsonPath('data.pharmacies.data.0.on_duty_today.mode', 'all_day')
            ->assertJsonPath('data.pharmacies.data.0.on_duty_today.date', '2026-10-08');
    }

    public function test_the_page_loads_its_shifts_in_one_query(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00', 'Europe/Skopje'));

        /** @return array{int, int} all queries, and the page's own shift lookups */
        $measure = function (int $pharmacies): array {
            foreach (range(1, $pharmacies) as $i) {
                $pharmacy = $this->pharmacy('Аптека '.$pharmacies.'-'.$i);
                $this->shift($pharmacy, '2026-10-08', $i % 2 === 0 ? PharmacyDutyShift::MODE_ALL_DAY : PharmacyDutyShift::MODE_ON_CALL);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/pharmacies?'.http_build_query(['city' => 'Битола', 'per_page' => 50]))->assertOk();
            $queries = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();

            // The lookup itself, not the ordering subquery inside the list query.
            $lookups = array_filter($queries, fn (string $sql): bool => preg_match('/^select [^()]* from "pharmacy_duty_shifts"/i', $sql) === 1);

            return [count($queries), count($lookups)];
        };

        // Warm the per-request settings so both measurements start alike.
        $this->getJson('/api/v1/pharmacies')->assertOk();

        [$few, $fewLookups] = $measure(2);
        [$many, $manyLookups] = $measure(12);

        $this->assertSame(1, $fewLookups);
        $this->assertSame(1, $manyLookups, 'today\'s shifts are one query for the page');
        $this->assertSame($few, $many, 'the query count does not grow with the page');
    }
}
