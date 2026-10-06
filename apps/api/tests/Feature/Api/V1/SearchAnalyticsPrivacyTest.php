<?php

namespace Tests\Feature\Api\V1;

use App\Models\AnalyticsEvent;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Owner decision 2026-10-06: what people search (on this platform, often
 * symptoms) must never be tied to who searched. Searches are counted only as
 * anonymous daily aggregates of a normalised term.
 */
class SearchAnalyticsPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_in_search_is_counted_without_any_link_to_the_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/search?q='.urlencode('Кардиолог'))
            ->assertOk();

        $this->assertFalse(AnalyticsEvent::query()->where('event', 'search.query')->exists());
        $this->assertFalse(AnalyticsEvent::query()->where('user_id', $user->id)->exists());

        $this->assertDatabaseHas('search_term_daily', [
            'term' => 'кардиолог',
            'count' => 1,
        ]);
        $this->assertSame(['id', 'date', 'term', 'count'], Schema::getColumnListing('search_term_daily'));
    }

    public function test_repeat_searches_increment_one_normalised_row_per_day(): void
    {
        $this->getJson('/api/v1/search?q='.urlencode('  Болки   во ГРАДИТЕ '))->assertOk();
        $this->getJson('/api/v1/search?q='.urlencode('болки во градите'))->assertOk();

        $rows = DB::table('search_term_daily')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('болки во градите', $rows[0]->term);
        $this->assertSame(2, (int) $rows[0]->count);
        $this->assertSame(Carbon::today()->toDateString(), Carbon::parse($rows[0]->date)->toDateString());
    }

    public function test_long_terms_are_truncated(): void
    {
        app(AnalyticsService::class)->recordSearchTerm(str_repeat('а', 200));

        $this->assertSame(64, mb_strlen((string) DB::table('search_term_daily')->value('term')));
    }

    public function test_top_searches_read_the_aggregates(): void
    {
        DB::table('search_term_daily')->insert([
            ['date' => Carbon::today()->toDateString(), 'term' => 'кардиолог', 'count' => 3],
            ['date' => Carbon::today()->subDays(2)->toDateString(), 'term' => 'кардиолог', 'count' => 2],
            ['date' => Carbon::today()->toDateString(), 'term' => 'аптека', 'count' => 4],
            ['date' => Carbon::today()->subDays(40)->toDateString(), 'term' => 'старо', 'count' => 99],
        ]);

        $service = app(AnalyticsService::class);

        $this->assertSame([
            ['query' => 'кардиолог', 'total' => 5],
            ['query' => 'аптека', 'total' => 4],
        ], $service->topSearchQueries(30, 10));

        $this->assertSame(9, $service->summaryForDays(30)['search_queries']);
    }

    public function test_the_migration_folds_existing_search_events_into_aggregates_and_deletes_them(): void
    {
        $migration = $this->migration();
        $migration->down();

        $user = User::factory()->create();
        $day = Carbon::parse('2026-09-01 10:00:00');

        foreach (['Кардиолог', ' кардиолог ', 'a', 'Аптека'] as $q) {
            AnalyticsEvent::query()->create([
                'event' => 'search.query',
                'user_id' => $user->id,
                'properties' => ['q' => $q],
                'occurred_at' => $day,
            ]);
        }
        AnalyticsEvent::query()->create([
            'event' => 'user.login',
            'user_id' => $user->id,
            'occurred_at' => $day,
        ]);

        $migration->up();

        $this->assertFalse(AnalyticsEvent::query()->where('event', 'search.query')->exists());
        $this->assertTrue(AnalyticsEvent::query()->where('event', 'user.login')->exists());

        $rows = DB::table('search_term_daily')->orderBy('term')->get()
            ->map(fn ($row): array => [Carbon::parse($row->date)->toDateString(), $row->term, (int) $row->count])
            ->all();

        $this->assertSame([
            ['2026-09-01', 'аптека', 1],
            ['2026-09-01', 'кардиолог', 2],
        ], $rows);
    }

    public function test_the_purge_expires_aggregates_after_a_year(): void
    {
        DB::table('search_term_daily')->insert([
            ['date' => Carbon::today()->subDays(400)->toDateString(), 'term' => 'старо', 'count' => 1],
            ['date' => Carbon::today()->subDays(200)->toDateString(), 'term' => 'ново', 'count' => 1],
        ]);

        $this->artisan('analytics:purge-old-events')->assertSuccessful();

        $this->assertSame(['ново'], DB::table('search_term_daily')->pluck('term')->all());
    }

    private function migration(): object
    {
        $files = glob(database_path('migrations/*_create_search_term_daily_table.php')) ?: [];
        $this->assertCount(1, $files);

        return require $files[0];
    }
}
