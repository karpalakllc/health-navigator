<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * The tracked page templates are listed twice: in the web tracker
 * (apps/web/src/lib/ux/routes.ts), which decides what to send, and in
 * config/ux.php, which decides what the API accepts. A template only one side
 * knows is either silently dropped or never sent.
 */
class UxRoutesParityTest extends TestCase
{
    public function test_the_web_tracker_and_the_api_list_the_same_page_templates(): void
    {
        $file = base_path('../web/src/lib/ux/routes.ts');

        if (! is_file($file)) {
            $this->markTestSkipped('The web app is not next to the API in this checkout.');
        }

        $source = (string) file_get_contents($file);
        $this->assertSame(1, preg_match('#// ux-routes:start(.*?)// ux-routes:end#s', $source, $block));
        preg_match_all('/"([^"]+)"/', $block[1], $matches);

        $this->assertSame(config('ux.routes'), $matches[1]);
    }
}
