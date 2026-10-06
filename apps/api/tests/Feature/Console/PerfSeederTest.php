<?php

namespace Tests\Feature\Console;

use App\Models\Doctor;
use App\Models\User;
use Database\Seeders\PerfSeeder;
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
}
