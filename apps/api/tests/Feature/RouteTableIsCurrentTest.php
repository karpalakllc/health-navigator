<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateRouteTable;
use Tests\TestCase;

/**
 * The endpoint table in docs/api-contract.md is the public contract, and it
 * drifted twice while hand-maintained — documenting the `registrations` limiter
 * on /auth/email/resend when the route actually uses api-verification-resend,
 * and omitting optional auth from the review reads.
 *
 * Regenerating is one command; this makes forgetting it fail.
 */
class RouteTableIsCurrentTest extends TestCase
{
    public function test_the_documented_route_table_matches_the_router(): void
    {
        $path = GenerateRouteTable::docPath();

        $this->assertFileExists($path);

        $doc = file_get_contents($path);
        $start = strpos($doc, GenerateRouteTable::BEGIN);
        $end = strpos($doc, GenerateRouteTable::END);

        $this->assertNotFalse($start, 'Generated-table markers are missing.');
        $this->assertNotFalse($end, 'Generated-table markers are missing.');

        $documented = trim(substr(
            $doc,
            $start + strlen(GenerateRouteTable::BEGIN),
            $end - $start - strlen(GenerateRouteTable::BEGIN),
        ));

        $this->assertSame(
            GenerateRouteTable::render(),
            $documented,
            'docs/api-contract.md is out of date. Run: php artisan docs:route-table --write',
        );
    }
}
