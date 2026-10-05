<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlatformPreflightCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'production';

        // A configuration that passes every check; each test breaks one thing.
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.debug' => false,
            'app.url' => 'https://api.zdravje360.mk',
            'trustedproxy.proxies' => '10.0.0.0/8,192.0.2.0/24',
            'zdravje.frontend_url' => 'https://zdravje360.mk',
            'zdravje.web_public_url' => 'https://zdravje360.mk',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.from.address' => 'noreply@zdravje360.mk',
            'queue.default' => 'redis',
            'cache.default' => 'redis',
            'session.secure' => true,
            'cors.allowed_origins' => ['https://zdravje360.mk'],
            'sanctum.expiration' => 43_200,
            'zdravje.admin.email' => 'ops@zdravje360.mk',
            'zdravje.seed.local_demo' => false,
            'sentry.dsn' => 'https://public@o0.ingest.sentry.io/0',
            'media.disk' => 's3',
            'scout.driver' => 'meilisearch',
            'scout.meilisearch.host' => 'https://search.zdravje360.mk',
            'scout.meilisearch.key' => 'secret',
        ]);
    }

    /** @return array{exit: int, errors: list<string>, warnings: list<string>} */
    private function preflight(): array
    {
        $exit = Artisan::call('platform:preflight', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        return [
            'exit' => $exit,
            'errors' => array_column($report['errors'], 'check'),
            'warnings' => array_column($report['warnings'], 'check'),
        ];
    }

    public function test_a_safe_production_configuration_passes(): void
    {
        $result = $this->preflight();

        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
        $this->assertSame(0, $result['exit']);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function unsafeConfigurations(): array
    {
        return [
            'missing app key' => [['app.key' => null], 'app.key'],
            'debug on' => [['app.debug' => true], 'app.debug'],
            'http app url' => [['app.url' => 'http://api.zdravje360.mk'], 'app.url'],
            'localhost app url' => [['app.url' => 'https://localhost'], 'app.url'],
            'proxies unset' => [['trustedproxy.proxies' => null], 'trustedproxy.proxies'],
            'proxies star' => [['trustedproxy.proxies' => '*'], 'trustedproxy.proxies'],
            'proxies double star' => [['trustedproxy.proxies' => '**'], 'trustedproxy.proxies'],
            'proxies any ipv4' => [['trustedproxy.proxies' => '10.0.0.0/8, 0.0.0.0/0'], 'trustedproxy.proxies'],
            'frontend url default' => [['zdravje.frontend_url' => 'http://127.0.0.1:3000'], 'zdravje.frontend_url'],
            'frontend url http' => [['zdravje.frontend_url' => 'http://zdravje360.mk'], 'zdravje.frontend_url'],
            'web public url default' => [['zdravje.web_public_url' => 'http://127.0.0.1:3000'], 'zdravje.web_public_url'],
            'log mailer' => [['mail.default' => 'log'], 'mail.default'],
            'array mailer' => [['mail.default' => 'array'], 'mail.default'],
            'no from address' => [['mail.from.address' => null], 'mail.from.address'],
            'tls mail scheme' => [['mail.mailers.smtp.scheme' => 'tls'], 'mail.mailers.smtp.scheme'],
            'sync queue' => [['queue.default' => 'sync'], 'queue.default'],
            'file cache' => [['cache.default' => 'file'], 'cache.default'],
            'array cache' => [['cache.default' => 'array'], 'cache.default'],
            'insecure session cookie' => [['session.secure' => null], 'session.secure'],
            'no cors origins' => [['cors.allowed_origins' => []], 'cors.allowed_origins'],
            'localhost cors origin' => [['cors.allowed_origins' => ['https://zdravje360.mk', 'http://localhost:3000']], 'cors.allowed_origins'],
            'wildcard cors origin' => [['cors.allowed_origins' => ['*']], 'cors.allowed_origins'],
            'tokens never expire' => [['sanctum.expiration' => null], 'sanctum.expiration'],
            'default admin email' => [['zdravje.admin.email' => 'admin@zdravje360.test'], 'zdravje.admin.email'],
            'demo seeding on' => [['zdravje.seed.local_demo' => true], 'zdravje.seed.local_demo'],
            'unknown media disk' => [['media.disk' => 'nope'], 'media.disk'],
            'meilisearch without key' => [['scout.meilisearch.key' => null], 'scout.meilisearch.key'],
            'meilisearch on localhost' => [['scout.meilisearch.host' => 'http://localhost:7700'], 'scout.meilisearch.host'],
        ];
    }

    /** @param  array<string, mixed>  $override */
    #[DataProvider('unsafeConfigurations')]
    public function test_each_unsafe_setting_fails_the_preflight(array $override, string $check): void
    {
        config($override);

        $result = $this->preflight();

        $this->assertSame([$check], array_values(array_unique($result['errors'])));
        $this->assertSame(1, $result['exit']);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function riskyConfigurations(): array
    {
        return [
            'no sentry dsn' => [['sentry.dsn' => null], 'sentry.dsn'],
            'public media disk' => [['media.disk' => 'public'], 'media.disk'],
            'unrecognised cache store' => [['cache.default' => 'octane'], 'cache.default'],
        ];
    }

    /** @param  array<string, mixed>  $override */
    #[DataProvider('riskyConfigurations')]
    public function test_risky_settings_warn_without_failing(array $override, string $check): void
    {
        config($override);

        $result = $this->preflight();

        $this->assertSame([], $result['errors']);
        $this->assertSame([$check], $result['warnings']);
        $this->assertSame(0, $result['exit']);
    }

    public function test_smtps_and_unset_mail_schemes_are_accepted(): void
    {
        foreach (['smtps', null] as $scheme) {
            config(['mail.mailers.smtp.scheme' => $scheme]);

            $this->assertSame([], $this->preflight()['errors']);
        }
    }

    public function test_meilisearch_credentials_are_only_required_when_it_is_the_driver(): void
    {
        config(['scout.driver' => 'database', 'scout.meilisearch.key' => null]);

        $this->assertSame([], $this->preflight()['errors']);
    }

    public function test_staging_is_enforced_like_production(): void
    {
        $this->app['env'] = 'staging';
        config(['app.debug' => true]);

        $this->assertSame(1, $this->preflight()['exit']);
    }

    public function test_findings_are_reported_but_not_enforced_locally(): void
    {
        $this->app['env'] = 'local';
        config(['app.debug' => true]);

        $result = $this->preflight();

        $this->assertSame(['app.debug'], $result['errors']);
        $this->assertSame(0, $result['exit']);
    }

    public function test_human_readable_output_separates_errors_and_warnings(): void
    {
        config(['app.debug' => true, 'sentry.dsn' => null]);

        $this->artisan('platform:preflight')
            ->expectsOutputToContain('[app.debug]')
            ->expectsOutputToContain('[sentry.dsn]')
            ->expectsOutputToContain('Preflight FAILED: 1 error(s), 1 warning(s)')
            ->assertExitCode(1);
    }
}
