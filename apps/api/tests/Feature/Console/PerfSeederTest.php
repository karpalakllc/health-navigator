<?php

namespace Tests\Feature\Console;

use App\Models\Doctor;
use App\Models\User;
use App\Support\RoleCatalog;
use App\Support\TaxonomyCache;
use Database\Seeders\PerfSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PerfSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function deployments(): array
    {
        return ['production' => ['production'], 'staging' => ['staging']];
    }

    #[DataProvider('deployments')]
    public function test_it_refuses_to_run_in_a_deployment(string $environment): void
    {
        $this->app['env'] = $environment;

        // Invoked directly: db:seed would stop at its own production prompt first.
        $this->app->make(PerfSeeder::class)->setContainer($this->app)->__invoke();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Doctor::query()->count());
    }

    /**
     * Seeded members must look like registered ones: the Member role (what
     * permissions are checked against) and a username (what public surfaces
     * render). Taxonomies written behind the models' backs must not be served
     * from a stale cache.
     */
    public function test_members_are_real_members_and_the_taxonomy_cache_is_flushed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $staleSpecialties = TaxonomyCache::remember(TaxonomyCache::SPECIALTIES, 'list', fn () => []);
        $this->assertSame([], $staleSpecialties);

        $this->app->make(PerfSeeder::class)->setContainer($this->app)->__invoke();

        $members = User::query()->where('email', 'like', '%@perf.zdravje360.test');
        $this->assertSame(PerfSeeder::MEMBERS, $members->count());
        $this->assertSame(0, (clone $members)->whereNull('username')->count());
        $this->assertSame(PerfSeeder::MEMBERS, User::role(RoleCatalog::MEMBER)->count());

        $member = (clone $members)->first();
        $this->assertMatchesRegularExpression('/^perf_member\\d+$/', $member->username);
        $this->assertSame($member->username, $member->publicName());
        $this->assertTrue($member->hasRole(RoleCatalog::MEMBER));
        $this->assertTrue($member->can('reviews.create'));

        $this->assertNotSame(
            [],
            TaxonomyCache::remember(TaxonomyCache::SPECIALTIES, 'list', fn () => ['fresh']),
        );
    }
}
