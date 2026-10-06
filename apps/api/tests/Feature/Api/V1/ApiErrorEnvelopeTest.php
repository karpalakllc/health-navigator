<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Every API error, including the ones the framework raises before a route (and
 * so before SetApiLocale) runs, uses the documented envelope:
 * { message, code, errors? } — see docs/api-contract.md (audit M15).
 */
class ApiErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_carry_a_code_and_keep_the_field_errors(): void
    {
        $this->withHeader('Accept-Language', 'mk')
            ->postJson('/api/v1/auth/login', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation.failed')
            // apps/web reads payload.errors.<field>[0] and falls back to payload.message.
            ->assertJsonPath('errors.email.0', 'Полето е-адреса мора да биде валидна е-адреса.')
            ->assertJsonStructure(['message', 'code', 'errors' => ['email', 'password']])
            ->assertJsonPath('message', 'Полето е-адреса мора да биде валидна е-адреса. (и уште 1 грешка)');
    }

    public function test_the_validation_summary_pluralises_in_macedonian(): void
    {
        $message = $this->withHeader('Accept-Language', 'mk')
            ->postJson('/api/v1/auth/register', [])
            ->assertStatus(422)
            ->json('message');

        $this->assertMatchesRegularExpression('/\(и уште \d+ грешки\)$/u', $message);
    }

    public function test_the_validation_summary_stays_english_for_english_clients(): void
    {
        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/auth/login', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation.failed')
            ->assertJsonPath('message', 'The email field must be a valid email address. (and 1 more error)');
    }

    public function test_an_unmatched_route_is_enveloped_and_macedonian_by_default(): void
    {
        // The test client sends `en-us` unless told otherwise; an unsupported
        // language is what exercises the default.
        $response = $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
            ->getJson('/api/v1/no-such-endpoint')
            ->assertNotFound()
            ->assertJsonPath('code', 'errors.not_found')
            ->assertJsonPath('message', 'Не е пронајдено.');

        $this->assertSame('mk', $response->headers->get('Content-Language'));
        $this->assertStringContainsString('Accept-Language', (string) $response->headers->get('Vary'));
    }

    public function test_an_unmatched_route_honours_accept_language(): void
    {
        $response = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/no-such-endpoint')
            ->assertNotFound()
            ->assertJsonPath('message', 'Not found.');

        $this->assertSame('en', $response->headers->get('Content-Language'));
    }

    public function test_a_matched_route_declares_its_language_only_once(): void
    {
        $response = $this->withHeader('Accept-Language', 'mk')
            ->getJson('/api/v1/doctors/no-such-doctor')
            ->assertNotFound();

        $this->assertSame(1, substr_count((string) $response->headers->get('Vary'), 'Accept-Language'));
    }

    public function test_a_wrong_method_is_enveloped(): void
    {
        $response = $this->withHeader('Accept-Language', 'mk')
            ->deleteJson('/api/v1/health')
            ->assertStatus(405)
            ->assertJsonPath('code', 'errors.method_not_allowed')
            ->assertJsonPath('message', 'Методот не е дозволен за оваа адреса.');

        $this->assertNotNull($response->headers->get('Allow'), 'The Allow header was dropped.');
    }

    public function test_rate_limited_requests_keep_retry_after(): void
    {
        Route::middleware(['api', 'throttle:1,1'])->get('api/v1/__throttle-probe', fn () => ['data' => true]);

        $this->getJson('/api/v1/__throttle-probe')->assertOk();

        $response = $this->withHeader('Accept-Language', 'mk')
            ->getJson('/api/v1/__throttle-probe')
            ->assertStatus(429)
            ->assertJsonPath('code', 'errors.too_many_requests')
            ->assertJsonPath('message', 'Премногу барања. Обидете се повторно подоцна.');

        $this->assertNotNull($response->headers->get('Retry-After'), 'Clients cannot back off without Retry-After.');
    }

    public function test_a_policy_denial_is_enveloped(): void
    {
        Route::middleware('api')->get('api/v1/__forbidden-probe', function () {
            throw new AuthorizationException;
        });

        $this->withHeader('Accept-Language', 'mk')
            ->getJson('/api/v1/__forbidden-probe')
            ->assertForbidden()
            ->assertJsonPath('code', 'errors.forbidden')
            ->assertJsonPath('message', 'Немате дозвола за оваа акција.');
    }

    public function test_a_policy_denial_keeps_its_own_message(): void
    {
        Route::middleware('api')->get('api/v1/__forbidden-probe', function () {
            throw new AuthorizationException('Само авторот може да ја уреди објавата.');
        });

        $this->getJson('/api/v1/__forbidden-probe')
            ->assertForbidden()
            ->assertJsonPath('code', 'errors.forbidden')
            ->assertJsonPath('message', 'Само авторот може да ја уреди објавата.');
    }

    public function test_a_server_error_is_enveloped_without_leaking_details(): void
    {
        config(['app.debug' => false]);

        Route::middleware('api')->get('api/v1/__boom-probe', function () {
            throw new RuntimeException('SQLSTATE secret internals');
        });

        $response = $this->withHeader('Accept-Language', 'mk')
            ->getJson('/api/v1/__boom-probe')
            ->assertStatus(500)
            ->assertJsonPath('code', 'errors.server_error')
            ->assertJsonPath('message', 'Настана грешка на серверот. Обидете се повторно подоцна.');

        $this->assertStringNotContainsString('secret internals', $response->getContent());
    }

    public function test_debug_mode_keeps_the_framework_trace_for_developers(): void
    {
        config(['app.debug' => true]);

        Route::middleware('api')->get('api/v1/__boom-probe', function () {
            throw new RuntimeException('visible in debug');
        });

        $this->getJson('/api/v1/__boom-probe')
            ->assertStatus(500)
            ->assertJsonPath('message', 'visible in debug');
    }
}
