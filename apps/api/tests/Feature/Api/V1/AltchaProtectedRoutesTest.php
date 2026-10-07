<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SolvesAltcha;
use Tests\TestCase;

/**
 * Every form open to automated abuse needs a solved ALTCHA challenge
 * (W7-C): sign-up, content reports, profile reports, corrections and
 * objections, claim requests. Without one the API refuses with 422 on
 * `altcha`, whoever is signed in.
 */
class AltchaProtectedRoutesTest extends TestCase
{
    use RefreshDatabase;
    use SolvesAltcha;

    /** method + URI of every route that must carry the `altcha` middleware. */
    private const PROTECTED = [
        'POST api/v1/auth/register',
        'POST api/v1/reviews/{review}/reports',
        'POST api/v1/forum/categories/{category}/topics/{topic}/reports',
        'POST api/v1/forum/posts/{post}/reports',
        'POST api/v1/doctors/{slug}/claim-requests',
        'POST api/v1/doctors/{slug}/corrections',
        'POST api/v1/facilities/{slug}/corrections',
        'POST api/v1/doctors/{slug}/profile-reports',
        'POST api/v1/facilities/{slug}/profile-reports',
        'POST api/v1/pharmacies/{slug}/profile-reports',
    ];

    public function test_each_protected_route_carries_the_altcha_middleware(): void
    {
        $found = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route): bool => in_array('altcha', $route->gatherMiddleware(), true))
            ->flatMap(fn (LaravelRoute $route): array => array_map(
                fn (string $method): string => $method.' '.$route->uri(),
                array_diff($route->methods(), ['HEAD']),
            ))
            ->sort()
            ->values()
            ->all();

        $expected = self::PROTECTED;
        sort($expected);

        $this->assertSame($expected, $found);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function protectedRequests(): array
    {
        return [
            'registration' => ['/api/v1/auth/register', false],
            'review report' => ['/api/v1/reviews/1/reports', true],
            'forum topic report' => ['/api/v1/forum/categories/opshto/topics/tema/reports', true],
            'forum reply report' => ['/api/v1/forum/posts/1/reports', true],
            'claim request' => ['/api/v1/doctors/ana-petrovska/claim-requests', true],
            'doctor correction' => ['/api/v1/doctors/ana-petrovska/corrections', false],
            'facility correction' => ['/api/v1/facilities/klinika/corrections', false],
            'doctor profile report' => ['/api/v1/doctors/ana-petrovska/profile-reports', false],
            'facility profile report' => ['/api/v1/facilities/klinika/profile-reports', false],
            'pharmacy profile report' => ['/api/v1/pharmacies/apteka/profile-reports', false],
        ];
    }

    #[DataProvider('protectedRequests')]
    public function test_a_request_without_a_solution_is_refused(string $uri, bool $member): void
    {
        $this->enableAltcha();

        if ($member) {
            $user = User::factory()->create();
            $user->assignRole(RoleCatalog::ensure(RoleCatalog::MEMBER));
            Sanctum::actingAs($user);
        }

        $this->postJson($uri, ['altcha' => 'bm90LWEtc29sdXRpb24='])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['altcha' => __('api.altcha.failed')]);

        $this->postJson($uri, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['altcha']);
    }
}
