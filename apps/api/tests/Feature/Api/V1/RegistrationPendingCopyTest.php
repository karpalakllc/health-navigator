<?php

namespace Tests\Feature\Api\V1;

use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N6: the 202 after sign-up is the same whether or not the address already has
 * an account, so its message must not claim a confirmation link was sent — an
 * existing account gets an "already registered" notice instead.
 */
class RegistrationPendingCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
        $this->forgetRateLimits();
    }

    public function test_the_registration_message_is_conditional_in_both_languages(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        foreach (['mk' => 'Ако адресата може да се користи', 'en' => 'If the address can be used'] as $locale => $prefix) {
            foreach (['taken@example.com', "new-{$locale}@example.com"] as $email) {
                $this->forgetRateLimits();

                $message = $this->withHeader('Accept-Language', $locale)
                    ->postJson('/api/v1/auth/register', [
                        'name' => 'New Member',
                        'display_name' => 'Нов Ч.',
                        'email' => $email,
                        'password' => 'sufficiently1long',
                        'password_confirmation' => 'sufficiently1long',
                    ])
                    ->assertStatus(202)
                    ->json('data.message');

                $this->assertStringStartsWith($prefix, $message);
            }
        }
    }
}
