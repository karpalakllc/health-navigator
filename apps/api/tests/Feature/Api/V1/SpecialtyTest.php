<?php

namespace Tests\Feature\Api\V1;

use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_published_specialties_ordered(): void
    {
        Specialty::factory()->create([
            'name' => 'Zebra',
            'slug' => 'zebra',
            'sort_order' => 2,
            'is_published' => true,
        ]);
        Specialty::factory()->create([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        Specialty::factory()->unpublished()->create([
            'name' => 'Hidden',
            'slug' => 'hidden',
        ]);

        $response = $this->getJson('/api/v1/specialties');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'alpha')
            ->assertJsonPath('data.1.slug', 'zebra')
            ->assertJsonCount(2, 'data');
    }
}
