<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The breach check (Have I Been Pwned) belongs to every deployed environment,
 * not only production; local dev and the test suite stay offline.
 */
class PasswordDefaultsTest extends TestCase
{
    private const BREACHED = 'breached1password';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $hash = strtoupper(sha1(self::BREACHED));
        Http::fake([
            'api.pwnedpasswords.com/range/'.substr($hash, 0, 5) => Http::response(substr($hash, 5).':4242'),
        ]);
    }

    private function passes(string $password): bool
    {
        return Validator::make(['password' => $password], ['password' => [Password::defaults()]])->passes();
    }

    /** @return array<string, array{string}> */
    public static function deployedEnvironments(): array
    {
        return [
            'production' => ['production'],
            'staging' => ['staging'],
        ];
    }

    #[DataProvider('deployedEnvironments')]
    public function test_deployed_environments_reject_breached_passwords(string $environment): void
    {
        $this->app['env'] = $environment;

        $this->assertFalse($this->passes(self::BREACHED));
        Http::assertSentCount(1);
    }

    /** @return array<string, array{string}> */
    public static function offlineEnvironments(): array
    {
        return [
            'local' => ['local'],
            'development' => ['development'],
            'testing' => ['testing'],
        ];
    }

    #[DataProvider('offlineEnvironments')]
    public function test_local_environments_skip_the_breach_check(string $environment): void
    {
        $this->app['env'] = $environment;

        $this->assertTrue($this->passes(self::BREACHED));
        Http::assertNothingSent();
    }
}
