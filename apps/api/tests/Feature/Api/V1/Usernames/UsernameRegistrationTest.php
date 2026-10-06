<?php

namespace Tests\Feature\Api\V1\Usernames;

use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Sign-up with a public username and the „14+ and I accept the terms“
 * consent, and the availability check the form uses while typing.
 */
class UsernameRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
        $this->forgetRateLimits();
        Notification::fake();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Марија Костовска',
            'username' => 'Bitolchanka',
            'email' => 'marija@example.com',
            'password' => 'sufficiently1long',
            'password_confirmation' => 'sufficiently1long',
            'accept_terms' => true,
        ], $overrides);
    }

    public function test_registration_stores_the_username_and_the_terms_acceptance(): void
    {
        $this->travelTo(now()->startOfSecond());

        $this->postJson('/api/v1/auth/register', $this->registration(['username' => '  Bitolchanka ']))
            ->assertStatus(202);

        $user = User::query()->where('email', 'marija@example.com')->sole();

        $this->assertSame('Bitolchanka', $user->username);
        $this->assertSame('bitolchanka', $user->username_normalized);
        $this->assertFalse($user->must_choose_username);
        $this->assertNull($user->username_changed_at);
        $this->assertSame('Марија Костовска', $user->name);
        $this->assertTrue($user->terms_accepted_at->equalTo(now()));
        $this->assertSame(config('zdravje.legal.terms_version'), $user->terms_version);
        $this->assertSame('Bitolchanka', $user->publicName());
    }

    public function test_the_terms_checkbox_is_required(): void
    {
        foreach ([null, false, 'no'] as $value) {
            $this->forgetRateLimits();
            $payload = $this->registration(['accept_terms' => $value]);

            $this->withHeader('Accept-Language', 'mk')
                ->postJson('/api/v1/auth/register', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['accept_terms' => 'најмалку 14 години']);
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_username_is_required_and_checked(): void
    {
        $payload = $this->registration();
        unset($payload['username']);

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');

        foreach (['Д-р.Марко' => 'не е дозволено', 'zdravje_tim' => 'не е дозволено', 'ab' => 'од 3 до 30', 'Аdmin' => 'само латиница или само кирилица'] as $username => $message) {
            $this->forgetRateLimits();
            $this->withHeader('Accept-Language', 'mk')
                ->postJson('/api/v1/auth/register', $this->registration(['username' => $username]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['username' => $message]);
        }
    }

    public function test_a_taken_username_is_refused_whatever_the_address(): void
    {
        User::factory()->create(['username' => 'Bitolchanka', 'email' => 'first@example.com']);

        foreach (['first@example.com', 'second@example.com'] as $email) {
            $this->forgetRateLimits();
            // The same answer for a registered and a free address: refusing a
            // public username says nothing about who owns an email address.
            $this->withHeader('Accept-Language', 'mk')
                ->postJson('/api/v1/auth/register', $this->registration(['email' => $email, 'username' => 'битолчанка']))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['username' => 'Ова корисничко име веќе се користи.']);
        }
    }

    public function test_the_availability_check_answers_yes_or_no_only(): void
    {
        User::factory()->create(['username' => 'Bitolchanka', 'name' => 'Марија Костовска', 'email' => 'm@example.com']);

        $this->getJson('/api/v1/usernames/availability?username=ana_free')
            ->assertOk()
            ->assertExactJson(['data' => ['available' => true, 'message' => null]]);

        $taken = $this->withHeader('Accept-Language', 'mk')
            ->getJson('/api/v1/usernames/availability?username=BITOLCHANKA')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.message', 'Ова корисничко име веќе се користи.');

        // Nothing about the account that holds it.
        foreach (['Марија', 'm@example.com', 'user', '"id"'] as $private) {
            $this->assertStringNotContainsString($private, (string) $taken->getContent());
        }

        $this->getJson('/api/v1/usernames/availability')
            ->assertOk()
            ->assertJsonPath('data.available', false);
        $this->getJson('/api/v1/usernames/availability?username[]=x')
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_a_members_own_username_is_available_to_them(): void
    {
        $member = User::factory()->create(['username' => 'Bitolchanka']);

        $this->actingAs($member)
            ->getJson('/api/v1/usernames/availability?username=bitolchanka')
            ->assertOk()
            ->assertJsonPath('data.available', true);
    }

    public function test_the_availability_check_is_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/v1/usernames/availability?username=ana_'.chr(97 + ($i % 26)))->assertOk();
        }

        $this->getJson('/api/v1/usernames/availability?username=ana_z')->assertStatus(429);
    }
}
