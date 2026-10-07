<?php

namespace Tests\Feature\UrgentCare;

use App\Support\Ux\UxSamplePath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * „Каде веднаш“ and the guides are on the UX heatmap allow-list (both sides:
 * UxRoutesParityTest), and the admin heatmap link opens a real page.
 */
class UrgentCareUxRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_new_templates_are_counted_and_have_sample_pages(): void
    {
        foreach (['/urgent-care', '/urgent-care/[city]', '/guides', '/guides/[slug]'] as $route) {
            $this->postJson('/api/v1/ux/events', [
                'clicks' => [],
                'views' => [['r' => $route, 'vc' => 'mobile', 's' => 50, 't' => 1]],
            ])->assertNoContent();
        }

        $this->assertSame(4, DB::table('ux_page_stats')->count());
        $this->assertSame('/urgent-care/skopje', UxSamplePath::for('/urgent-care/[city]'));
        $this->assertSame('/guides/kako-do-uput', UxSamplePath::for('/guides/[slug]'));
    }
}
