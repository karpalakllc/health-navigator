<?php

namespace Tests\Feature\Api\V1;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The API used to return a mix of hardcoded English and Macedonian strings
 * (audit M15). These lock in the replacement contract: a localised `message`
 * plus a stable machine-readable `code` that clients can translate themselves.
 */
class ApiLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_error_envelope_carries_a_stable_code(): void
    {
        $this->getJson('/api/v1/doctors/no-such-doctor')
            ->assertNotFound()
            ->assertJsonPath('code', 'errors.not_found');
    }

    public function test_messages_are_macedonian_when_requested(): void
    {
        $this->withHeader('Accept-Language', 'mk')
            ->getJson('/api/v1/doctors/no-such-doctor')
            ->assertNotFound()
            ->assertJsonPath('code', 'errors.not_found')
            ->assertJsonPath('message', 'Не е пронајдено.');
    }

    public function test_messages_are_english_when_requested(): void
    {
        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/doctors/no-such-doctor')
            ->assertNotFound()
            ->assertJsonPath('message', 'Not found.');
    }

    public function test_unsupported_language_falls_back_to_macedonian(): void
    {
        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
            ->getJson('/api/v1/doctors/no-such-doctor')
            ->assertNotFound()
            ->assertJsonPath('message', 'Не е пронајдено.');
    }

    public function test_responses_declare_their_language_and_vary_on_it(): void
    {
        $response = $this->withHeader('Accept-Language', 'mk')->getJson('/api/v1/health');

        $this->assertSame('mk', $response->headers->get('Content-Language'));
        $this->assertStringContainsString('Accept-Language', (string) $response->headers->get('Vary'));
    }

    public function test_module_gate_is_localised_and_coded(): void
    {
        SiteSetting::current()->update(['public_products' => false]);

        $this->withHeader('Accept-Language', 'mk')
            ->getJson('/api/v1/products')
            ->assertStatus(503)
            ->assertJsonPath('code', 'module.unavailable')
            ->assertJsonPath('message', 'Овој дел сè уште не е достапен.');
    }

    public function test_validation_messages_are_localised(): void
    {
        $this->withHeader('Accept-Language', 'mk')
            ->postJson('/api/v1/auth/login', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Полето е-адреса мора да биде валидна е-адреса.');
    }

    public function test_invalid_credentials_are_localised_and_generic(): void
    {
        $this->withHeader('Accept-Language', 'mk')
            ->postJson('/api/v1/auth/login', [
                'email' => 'nobody@example.com',
                'password' => 'whatever-password',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Внесените податоци за најава се неточни.');
    }
}
