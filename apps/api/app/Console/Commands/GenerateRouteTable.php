<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Regenerates the endpoint table in docs/api-contract.md from the router.
 *
 * The table was hand-maintained and drifted twice — most recently documenting
 * the wrong limiter on /auth/email/resend and omitting optional auth on the
 * review reads. A table nobody can regenerate is a table that silently rots,
 * and this one is the public contract.
 *
 * RouteTableIsCurrentTest asserts the committed file matches this output, so
 * drift fails the suite rather than shipping.
 */
class GenerateRouteTable extends Command
{
    protected $signature = 'docs:route-table {--write : Rewrite the table in docs/api-contract.md}';

    protected $description = 'Regenerate the API endpoint table from the router';

    public const BEGIN = '<!-- BEGIN generated route table -->';

    public const END = '<!-- END generated route table -->';

    public function handle(): int
    {
        $table = self::render();

        if (! $this->option('write')) {
            $this->line($table);

            return self::SUCCESS;
        }

        $path = self::docPath();
        $doc = file_get_contents($path);

        $start = strpos($doc, self::BEGIN);
        $end = strpos($doc, self::END);

        if ($start === false || $end === false) {
            $this->error('Markers not found in '.$path);

            return self::FAILURE;
        }

        $updated = substr($doc, 0, $start)
            .self::BEGIN."\n".$table."\n".self::END
            .substr($doc, $end + strlen(self::END));

        file_put_contents($path, $updated);
        $this->info('Rewrote the route table in '.$path);

        return self::SUCCESS;
    }

    public static function docPath(): string
    {
        return base_path('../../docs/api-contract.md');
    }

    /** The table body, without the surrounding markers. */
    public static function render(): string
    {
        $rows = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }

            $path = '/'.substr($route->uri(), strlen('api/v1/'));

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $rows[$method.' '.$path] = sprintf(
                    '| `%s` | `%s` | %s |',
                    $method,
                    $path,
                    self::guards($route),
                );
            }
        }

        ksort($rows);

        return implode("\n", array_merge(
            ['| Method | Path | Guards |', '|--------|------|--------|'],
            array_values($rows),
        ));
    }

    private static function guards(RoutingRoute $route): string
    {
        $guards = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || $middleware === 'api') {
                continue;
            }

            $guards[] = '`'.$middleware.'`';
        }

        return $guards === [] ? '—' : implode(', ', $guards);
    }
}
