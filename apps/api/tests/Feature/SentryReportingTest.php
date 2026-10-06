<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Sentry\Event;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Tests\TestCase;

/**
 * sentry/sentry-laravel removes the PHP SDK's own error listeners and expects
 * the app to hand exceptions over in bootstrap/app.php. Without that hook the
 * DSN, config file and package were all present and nothing was ever sent.
 *
 * These run the real client end to end with only the HTTP transport swapped.
 */
class SentryReportingTest extends TestCase
{
    use RefreshDatabase;

    private const DSN = 'https://public@sentry.invalid/1';

    private object $transport;

    protected function setUp(): void
    {
        // The SDK only installs its request-capturing middleware when a DSN is
        // configured at boot, so set it the way production does — in the env.
        putenv('SENTRY_LARAVEL_DSN='.self::DSN);
        $_ENV['SENTRY_LARAVEL_DSN'] = $_SERVER['SENTRY_LARAVEL_DSN'] = self::DSN;

        parent::setUp();

        $this->transport = new class implements TransportInterface
        {
            /** @var list<Event> */
            public array $events = [];

            public function send(Event $event): Result
            {
                $this->events[] = $event;

                return new Result(ResultStatus::success(), $event);
            }

            public function close(?int $timeout = null): Result
            {
                return new Result(ResultStatus::success());
            }
        };

        config(['sentry.transport' => $this->transport]);

        // The hub was built during boot with the real HTTP transport; rebuild it.
        $this->app->forgetInstance(HubInterface::class);
        $this->app->make(HubInterface::class);

        Route::middleware('api')->post('api/v1/__sentry-probe', static function () {
            throw new RuntimeException('sentry probe');
        });
    }

    protected function tearDown(): void
    {
        putenv('SENTRY_LARAVEL_DSN');
        unset($_ENV['SENTRY_LARAVEL_DSN'], $_SERVER['SENTRY_LARAVEL_DSN']);

        parent::tearDown();
    }

    /**
     * infra/deploy.md runs `config:cache`, which refuses a closure anywhere in
     * config — the previous inline before_send made every deploy fail there.
     */
    public function test_the_before_send_hook_survives_config_caching(): void
    {
        $hook = config('sentry.before_send');

        $this->assertNotInstanceOf(Closure::class, $hook);
        $this->assertIsCallable($hook);
        $this->assertSame($hook, eval('return '.var_export($hook, true).';'));
    }

    public function test_an_unhandled_api_exception_is_sent_to_sentry(): void
    {
        $this->postJson('/api/v1/__sentry-probe')->assertStatus(500);

        $this->assertCount(1, $this->transport->events, 'The exception never reached Sentry.');

        $exceptions = $this->transport->events[0]->getExceptions();
        $this->assertSame('sentry probe', $exceptions[0]->getValue());
    }

    public function test_credentials_are_scrubbed_from_the_reported_request(): void
    {
        $this->assertFalse((bool) config('sentry.send_default_pii'));

        $this->withHeader('Authorization', 'Bearer live-bearer-secret')
            ->postJson('/api/v1/__sentry-probe?token=query-secret&page=2', [
                'email' => 'person@example.com',
                'password' => 'body-secret-1',
                'password_confirmation' => 'body-secret-2',
                'current_password' => 'body-secret-3',
                'profile' => ['reset_token' => 'body-secret-4', 'name' => 'Ана'],
            ])
            ->assertStatus(500);

        $this->assertCount(1, $this->transport->events);
        $request = $this->transport->events[0]->getRequest();
        $serialised = json_encode($request, JSON_UNESCAPED_UNICODE);

        foreach (['body-secret', 'query-secret', 'live-bearer-secret', 'person@example.com'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialised, "[{$secret}] leaked to Sentry.");
        }

        // Scrubbing must not throw away the context that makes an event useful.
        $this->assertSame('Ана', $request['data']['profile']['name']);
        $this->assertStringContainsString('page=2', $request['query_string']);
    }
}
