<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invalid UTF-8 in a query string or body reached the database, where
 * PostgreSQL rejects it (SQLSTATE 22021) and the request became a 500. It is a
 * client error, answered with the ordinary 422 validation envelope.
 */
class InvalidUtf8InputTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_utf8_in_the_query_string_is_a_validation_error(): void
    {
        $this->getJson('/api/v1/search?q=ana&city=%D1%5C')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation.failed')
            ->assertJsonValidationErrors(['city']);

        $this->getJson('/api/v1/doctors?q=%D1%5C')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    public function test_invalid_utf8_nested_in_a_form_body_is_a_validation_error(): void
    {
        $this->post('/api/v1/auth/login', ['email' => "a\xD1\x5C@example.com", 'password' => 'x'], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->put('/api/v1/triage/sessions/1/answers', ['answers' => [['values' => ["\xFF"]]]], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers.0.values.0']);
    }

    public function test_invalid_utf8_in_the_path_is_a_client_error(): void
    {
        // Laravel's global ValidatePathEncoding answers this before routing.
        $this->getJson('/api/v1/doctors/%D1%5C')->assertBadRequest();
    }

    public function test_valid_cyrillic_input_is_untouched(): void
    {
        $this->getJson('/api/v1/search?q='.rawurlencode('Скопје').'&city='.rawurlencode('Струга'))
            ->assertOk();
    }
}
