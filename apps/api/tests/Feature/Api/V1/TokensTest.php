<?php

namespace Tests\Feature\Api\V1;

use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * D6: a member sees their signed-in devices and can sign any one of them out,
 * or every device but the current one. Nobody can see or revoke another
 * member's tokens.
 */
class TokensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SiteSetting::current();
        $this->forgetRateLimits();
    }

    private function as(string $token, string $method, string $uri): TestResponse
    {
        // A fresh guard per request, as in production.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->json($method, $uri);
    }

    public function test_login_names_the_token_after_the_device(): void
    {
        User::factory()->create(['email' => 'member@example.com', 'password' => 'sufficiently1long']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.com',
            'password' => 'sufficiently1long',
            'device_name' => 'Firefox · Linux',
        ])->assertOk();

        $this->assertSame(['Firefox · Linux'], PersonalAccessToken::query()->pluck('name')->all());
    }

    public function test_the_list_shows_own_active_devices_with_the_current_one_first(): void
    {
        $member = User::factory()->create();
        $phone = $member->createToken('Safari · iOS');
        $phone->accessToken->forceFill(['last_used_at' => now()->subDay()])->save();
        $laptop = $member->createToken('Firefox · Linux')->plainTextToken;

        // Expired by the global window: not a device any more.
        $stale = $member->createToken('Old browser');
        $stale->accessToken->forceFill(['created_at' => now()->subMinutes((int) config('sanctum.expiration') + 1)])->save();

        $someoneElse = User::factory()->create()->createToken('Edge · Windows');

        $response = $this->as($laptop, 'GET', '/api/v1/me/tokens')->assertOk();

        $this->assertSame(['Firefox · Linux', 'Safari · iOS'], array_column($response->json('data.tokens'), 'name'));
        $response->assertJsonPath('data.tokens.0.is_current', true)
            ->assertJsonPath('data.tokens.1.is_current', false)
            ->assertJsonPath('data.tokens.1.id', $phone->accessToken->getKey())
            ->assertJsonStructure(['data' => ['tokens' => [['id', 'name', 'created_at', 'last_used_at', 'expires_at', 'is_current']]]])
            ->assertDontSee('Edge · Windows')
            ->assertDontSee((string) $someoneElse->accessToken->token);

        $this->assertNotNull($response->json('data.tokens.1.last_used_at'));
        $this->assertNotNull($response->json('data.tokens.0.expires_at'));
    }

    /** NULLs sort first on PostgreSQL and last on SQLite: never-used devices go last on both. */
    public function test_never_used_devices_are_listed_after_used_ones(): void
    {
        $member = User::factory()->create();
        $current = $member->createToken('Firefox · Linux')->plainTextToken;
        $used = $member->createToken('Safari · iOS');
        $used->accessToken->forceFill(['last_used_at' => now()->subDay()])->save();
        $member->createToken('Chrome · Android');

        $response = $this->as($current, 'GET', '/api/v1/me/tokens')->assertOk();

        $this->assertSame(['Firefox · Linux', 'Safari · iOS', 'Chrome · Android'], array_column($response->json('data.tokens'), 'name'));
    }

    public function test_revoking_one_device_signs_it_out(): void
    {
        $member = User::factory()->create();
        $phone = $member->createToken('Safari · iOS');
        $laptop = $member->createToken('Firefox · Linux')->plainTextToken;

        $this->as($laptop, 'DELETE', '/api/v1/me/tokens/'.$phone->accessToken->getKey())
            ->assertOk()
            ->assertJsonPath('data.message', __('api.account.token_revoked'));

        $this->as($phone->plainTextToken, 'GET', '/api/v1/me')->assertUnauthorized();
        $this->as($laptop, 'GET', '/api/v1/me')->assertOk();
    }

    public function test_another_members_token_cannot_be_revoked_or_probed(): void
    {
        $victimToken = User::factory()->create()->createToken('Safari · iOS');
        $attacker = User::factory()->create()->createToken('Firefox · Linux')->plainTextToken;

        $this->as($attacker, 'DELETE', '/api/v1/me/tokens/'.$victimToken->accessToken->getKey())
            ->assertNotFound()
            ->assertJsonPath('code', 'errors.not_found');

        // Same answer as an id that does not exist.
        $this->as($attacker, 'DELETE', '/api/v1/me/tokens/999999')->assertNotFound();

        $this->assertNotNull(PersonalAccessToken::find($victimToken->accessToken->getKey()));
        $this->as($victimToken->plainTextToken, 'GET', '/api/v1/me')->assertOk();
    }

    public function test_revoking_all_others_keeps_the_current_device_and_other_members(): void
    {
        $member = User::factory()->create();
        $member->createToken('Safari · iOS');
        $member->createToken('Chrome · Android');
        $current = $member->createToken('Firefox · Linux')->plainTextToken;
        $bystander = User::factory()->create()->createToken('Edge · Windows');

        $this->as($current, 'DELETE', '/api/v1/me/tokens')
            ->assertOk()
            ->assertJsonPath('data.revoked', 2);

        $this->assertSame(['Firefox · Linux'], $member->tokens()->pluck('name')->all());
        $this->as($current, 'GET', '/api/v1/me')->assertOk();
        $this->as($bystander->plainTextToken, 'GET', '/api/v1/me')->assertOk();
    }

    public function test_the_device_endpoints_require_a_session(): void
    {
        $this->getJson('/api/v1/me/tokens')->assertUnauthorized();
        $this->deleteJson('/api/v1/me/tokens')->assertUnauthorized();
        $this->deleteJson('/api/v1/me/tokens/1')->assertUnauthorized();
    }
}
