<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every named rate limiter — registered with RateLimiter::for() or inline as
 * `throttle:<max>,<minutes>,<prefix>` on a route — has a row in the
 * docs/api-contract.md „Rate limits“ table, so clients can rely on it.
 */
class ApiContractLimitersTest extends TestCase
{
    public function test_every_named_limiter_is_documented(): void
    {
        $contract = (string) file_get_contents(base_path('../../docs/api-contract.md'));
        $table = Str::between($contract, '## Rate limits', '## Endpoints');
        $names = [];

        foreach ([app_path(), base_path('bootstrap')] as $directory) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    preg_match_all("/RateLimiter::for\\('([a-z0-9-]+)'/", (string) file_get_contents($file->getPathname()), $matches);
                    array_push($names, ...$matches[1]);
                }
            }
        }

        preg_match_all('/throttle:\d+,\d+,([a-z0-9-]+)/', (string) file_get_contents(base_path('routes/api.php')), $inline);
        $names = array_values(array_diff(array_unique([...$names, ...$inline[1]]), ['api']));

        $this->assertNotEmpty($names);

        foreach ($names as $name) {
            $this->assertStringContainsString('`'.$name.'`', $table, "Limiter {$name} has no row in docs/api-contract.md › Rate limits.");
        }
    }
}
