<?php

namespace Tests\Feature\Api\V1;

use App\Support\Ux\UxSchema;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * POST /ux/events (docs/ux-heatmaps.md): tracker batches become anonymous
 * daily counters, and nothing else.
 */
class UxEventsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function click(array $overrides = []): array
    {
        return [
            'r' => '/doctors/[slug]',
            'vc' => 'mobile',
            'wb' => 320,
            'x' => 42,
            'y' => 31,
            'k' => 'doctor-card/heading',
            'd' => true,
            'g' => false,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pageView(array $overrides = []): array
    {
        return ['r' => '/doctors/[slug]', 'vc' => 'mobile', 's' => 75, 't' => 1, ...$overrides];
    }

    public function test_a_batch_increments_the_daily_counters(): void
    {
        $this->postJson('/api/v1/ux/events', [
            'clicks' => [
                $this->click(),
                $this->click(['g' => true]),
                $this->click(['k' => 'doctor-card/link', 'd' => false, 'x' => 10, 'y' => 40]),
                // A fixed tab bar: counted for its target, not placed on the map.
                $this->click(['k' => 'tab-bar/link', 'd' => false, 'y' => null]),
            ],
            'views' => [$this->pageView(), $this->pageView(['s' => 100, 't' => null])],
        ])->assertNoContent();

        $today = Carbon::today()->toDateString();

        $cell = DB::table('ux_heatmap_cells')->where(['x_bucket' => 42, 'y_bucket' => 31])->first();
        $this->assertNotNull($cell);
        $this->assertSame([2, 2, 1], [(int) $cell->clicks, (int) $cell->dead_clicks, (int) $cell->rage_clicks]);
        $this->assertSame($today, Carbon::parse($cell->day)->toDateString());
        $this->assertSame(2, DB::table('ux_heatmap_cells')->count());

        $heading = DB::table('ux_element_stats')->where('target_key', 'doctor-card/heading')->first();
        $this->assertSame([2, 2, 1], [(int) $heading->clicks, (int) $heading->dead_clicks, (int) $heading->rage_clicks]);
        $this->assertSame(1, (int) DB::table('ux_element_stats')->where('target_key', 'tab-bar/link')->value('clicks'));

        $page = DB::table('ux_page_stats')->first();
        $this->assertSame(2, (int) $page->views);
        $this->assertSame([2, 2, 2, 1, 1], [
            (int) $page->scroll_25, (int) $page->scroll_50, (int) $page->scroll_75,
            (int) $page->scroll_90, (int) $page->scroll_100,
        ]);
        $this->assertSame(1, (int) $page->tfi_1_3s);
        $this->assertSame(1, (int) $page->tfi_none);
    }

    public function test_later_batches_add_to_the_same_rows(): void
    {
        $batch = ['clicks' => [$this->click()], 'views' => [$this->pageView()]];

        $this->postJson('/api/v1/ux/events', $batch)->assertNoContent();
        $this->postJson('/api/v1/ux/events', $batch)->assertNoContent();

        $this->assertSame(1, DB::table('ux_heatmap_cells')->count());
        $this->assertSame(2, (int) DB::table('ux_heatmap_cells')->value('clicks'));
        $this->assertSame(2, (int) DB::table('ux_element_stats')->value('dead_clicks'));
        $this->assertSame(2, (int) DB::table('ux_page_stats')->value('views'));
    }

    public function test_the_tables_hold_no_visitor_session_address_or_time(): void
    {
        $columns = array_merge(
            Schema::getColumnListing('ux_heatmap_cells'),
            Schema::getColumnListing('ux_element_stats'),
            Schema::getColumnListing('ux_page_stats'),
        );

        foreach (['user_id', 'session_id', 'ip', 'ip_address', 'user_agent', 'created_at', 'occurred_at', 'text', 'url'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidClicks(): array
    {
        return [
            'unknown page' => [['r' => '/account']],
            'a real slug instead of the template' => [['r' => '/doctors/ivan-petrov']],
            'query string' => [['r' => '/search?q=болка']],
            'free text as target key' => [['k' => 'main/Болка во градите']],
            'target key with spaces' => [['k' => 'doctor card/heading']],
            'well-formed words outside the vocabulary' => [['k' => 'secret-word/link']],
            'unknown element kind' => [['k' => 'main/whatever']],
            'unknown role' => [['k' => 'main/role-bogus']],
            'unknown input type' => [['k' => 'main/input-hidden']],
            'x out of range' => [['x' => 100]],
            'y out of range' => [['y' => 2000]],
            'width not on the step' => [['wb' => 390]],
            'unknown device class' => [['vc' => 'watch']],
            'extra field' => [['text' => 'Кликнав тука']],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidClicks')]
    public function test_out_of_vocabulary_values_fail_the_whole_batch(array $overrides): void
    {
        $this->postJson('/api/v1/ux/events', [
            'clicks' => [$this->click(), $this->click($overrides)],
            'views' => [],
        ])->assertUnprocessable();

        $this->assertSame(0, DB::table('ux_heatmap_cells')->count());
        $this->assertSame(0, DB::table('ux_element_stats')->count());
    }

    public function test_every_target_key_the_tracker_can_produce_is_accepted(): void
    {
        $keys = ['doctor-card/input-other', 'main/role-tab', 'page/area', 'home-how-it-works/focusable', 'tab-bar/link'];

        $this->postJson('/api/v1/ux/events', [
            'clicks' => array_map(fn (string $k): array => $this->click(['k' => $k]), $keys),
            'views' => [],
        ])->assertNoContent();

        $this->assertEqualsCanonicalizing($keys, DB::table('ux_element_stats')->pluck('target_key')->all());

        // The longest key the lists allow fits the length limit.
        $longest = collect(UxSchema::targetKeys())->sortByDesc(fn (string $k): int => strlen($k))->first();
        $this->postJson('/api/v1/ux/events', ['clicks' => [$this->click(['k' => $longest])], 'views' => []])
            ->assertNoContent();
    }

    public function test_views_are_validated_too(): void
    {
        $this->postJson('/api/v1/ux/events', ['clicks' => [], 'views' => [$this->pageView(['s' => 60])]])
            ->assertUnprocessable();
        $this->postJson('/api/v1/ux/events', ['clicks' => [], 'views' => [$this->pageView(['t' => 5])]])
            ->assertUnprocessable();
        $this->postJson('/api/v1/ux/events', ['clicks' => [], 'views' => []])
            ->assertUnprocessable();

        $this->assertSame(0, DB::table('ux_page_stats')->count());
    }

    public function test_batches_are_bounded(): void
    {
        $this->postJson('/api/v1/ux/events', [
            'clicks' => array_fill(0, 51, $this->click()),
            'views' => [],
        ])->assertUnprocessable();
    }

    public function test_the_endpoint_is_rate_limited_per_address(): void
    {
        $batch = ['clicks' => [], 'views' => [$this->pageView()]];

        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/v1/ux/events', $batch)->assertNoContent();
        }

        $this->postJson('/api/v1/ux/events', $batch)->assertTooManyRequests();
        $this->assertSame(60, (int) DB::table('ux_page_stats')->value('views'));
    }

    public function test_one_ipv6_network_shares_one_limit(): void
    {
        $batch = ['clicks' => [], 'views' => [$this->pageView()]];

        // An IPv6 visitor usually holds a whole /64 and could rotate through it.
        for ($i = 0; $i < 60; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2::'.dechex($i + 1)])
                ->postJson('/api/v1/ux/events', $batch)->assertNoContent();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2:ffff::1'])
            ->postJson('/api/v1/ux/events', $batch)->assertTooManyRequests();
        // Another network is not affected.
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:3::1'])
            ->postJson('/api/v1/ux/events', $batch)->assertNoContent();
    }

    public function test_the_limiter_key_cannot_be_reversed_to_the_address(): void
    {
        // The database cache store keeps limiter keys as rows: a plain hash of
        // the address there could be reversed by trying every IPv4 address.
        $limiter = app(RateLimiter::class);
        $onDatabase = new RateLimiter(Cache::store('database'));
        $named = (fn (): array => $this->limiters)->call($limiter);
        (function () use ($named): void {
            $this->limiters = $named;
        })->call($onDatabase);
        $this->app->instance(RateLimiter::class, $onDatabase);
        $ip = '203.0.113.7';

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/ux/events', ['clicks' => [], 'views' => [$this->pageView()]])
            ->assertNoContent();

        $keys = DB::table('cache')->pluck('key')->implode("\n");
        $this->assertNotSame('', $keys);

        foreach ([$ip, sha1($ip), sha1('|'.$ip), md5($ip), hash('sha256', $ip)] as $reversible) {
            $this->assertStringNotContainsString($reversible, $keys);
        }

        // Only an HMAC under the app key leads back to it.
        $hmac = hash_hmac('sha256', 'ux-events|'.$ip, (string) config('app.key'));
        $this->assertStringContainsString(md5('api-ux-events'.'ux-min:'.$hmac), $keys);
    }
}
