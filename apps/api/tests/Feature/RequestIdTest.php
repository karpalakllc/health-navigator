<?php

namespace Tests\Feature;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Tests\TestCase;

/**
 * Every response carries X-Request-Id; a well-formed one from the web tier is
 * echoed, anything else is replaced, and the ID is on every log record.
 */
class RequestIdTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/';

    public function test_a_response_without_an_incoming_id_gets_a_fresh_uuid(): void
    {
        $first = $this->getJson('/api/v1/health')->assertOk()->headers->get('X-Request-Id');
        $second = $this->getJson('/api/v1/health')->assertOk()->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression(self::UUID, (string) $first);
        $this->assertMatchesRegularExpression(self::UUID, (string) $second);
        $this->assertNotSame($first, $second);
    }

    public function test_a_well_formed_incoming_id_is_echoed(): void
    {
        $id = '3f2b8c1e-7a4d-4e9b-9c1a-0d5e6f7a8b9c';

        $this->getJson('/api/v1/health', ['X-Request-Id' => $id])
            ->assertOk()
            ->assertHeader('X-Request-Id', $id);

        $this->getJson('/api/v1/health', ['X-Request-Id' => 'render-ABC123'])
            ->assertHeader('X-Request-Id', 'render-ABC123');
    }

    public function test_a_malformed_incoming_id_is_replaced(): void
    {
        foreach (['short', str_repeat('a', 65), "abcd1234\nforged log line", 'abc_def.ghi', 'a b c d e f g h'] as $bad) {
            $returned = $this->getJson('/api/v1/health', ['X-Request-Id' => $bad])
                ->headers->get('X-Request-Id');

            $this->assertNotSame($bad, $returned);
            $this->assertMatchesRegularExpression(self::UUID, (string) $returned, 'for '.json_encode($bad));
        }
    }

    public function test_errors_carry_it_too(): void
    {
        $this->getJson('/api/v1/no-such-route', ['X-Request-Id' => 'abcdef12-error'])
            ->assertNotFound()
            ->assertHeader('X-Request-Id', 'abcdef12-error');
    }

    public function test_log_records_written_during_the_request_carry_the_id(): void
    {
        config(['logging.channels.request-id-test' => [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
        ]]);
        config(['logging.default' => 'request-id-test']);

        Route::get('/api/v1/__log-probe', function () {
            Log::info('probe');

            return response()->json(['ok' => true]);
        });

        $this->getJson('/api/v1/__log-probe', ['X-Request-Id' => 'probe-12345678'])->assertOk();

        /** @var TestHandler $handler */
        $handler = Log::channel('request-id-test')->getLogger()->getHandlers()[0];
        $records = array_filter($handler->getRecords(), fn (LogRecord $r): bool => $r->message === 'probe');

        $this->assertCount(1, $records);
        $this->assertSame('probe-12345678', array_values($records)[0]->extra[AssignRequestId::CONTEXT_KEY] ?? null);
    }

    public function test_log_format_json_switches_the_file_channels_to_json(): void
    {
        $this->assertNull(config('logging.channels.daily.formatter'));

        putenv('LOG_FORMAT=json');
        $_ENV['LOG_FORMAT'] = $_SERVER['LOG_FORMAT'] = 'json';

        try {
            $config = require config_path('logging.php');
        } finally {
            putenv('LOG_FORMAT');
            unset($_ENV['LOG_FORMAT'], $_SERVER['LOG_FORMAT']);
        }

        $this->assertSame(JsonFormatter::class, $config['channels']['daily']['formatter']);
        $this->assertSame(JsonFormatter::class, $config['channels']['single']['formatter']);
        $this->assertSame(JsonFormatter::class, $config['channels']['stderr']['formatter']);
    }
}
