<?php

namespace Tests\Unit;

use App\Support\Ux\UxSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The tracker's vocabulary is listed twice: in the web tracker
 * (apps/web/src/lib/ux/routes.ts and schema.ts), which decides what to send,
 * and in the API (config/ux.php, UxSchema), which decides what it accepts. A
 * word only one side knows is either silently dropped or never sent.
 */
class UxRoutesParityTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function webList(string $file, string $marker): array
    {
        $path = base_path('../web/src/lib/ux/'.$file);

        if (! is_file($path)) {
            $this->markTestSkipped('The web app is not next to the API in this checkout.');
        }

        $source = (string) file_get_contents($path);
        $this->assertSame(1, preg_match('#// '.$marker.':start(.*?)// '.$marker.':end#s', $source, $block), "No {$marker} block in {$file}.");
        preg_match_all('/"([^"]+)"/', $block[1], $matches);

        return $matches[1];
    }

    public function test_the_web_tracker_and_the_api_list_the_same_page_templates(): void
    {
        $this->assertSame(config('ux.routes'), $this->webList('routes.ts', 'ux-routes'));
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function targetLists(): array
    {
        return [
            'contexts' => ['ux-target-contexts', UxSchema::TARGET_CONTEXTS],
            'elements' => ['ux-target-elements', UxSchema::TARGET_ELEMENTS],
            'input types' => ['ux-input-types', UxSchema::INPUT_TYPES],
            'interactive roles' => ['ux-interactive-roles', UxSchema::INTERACTIVE_ROLES],
        ];
    }

    /**
     * @param  list<string>  $api
     */
    #[DataProvider('targetLists')]
    public function test_the_web_tracker_and_the_api_list_the_same_target_words(string $marker, array $api): void
    {
        $this->assertSame($api, $this->webList('schema.ts', $marker));
    }
}
