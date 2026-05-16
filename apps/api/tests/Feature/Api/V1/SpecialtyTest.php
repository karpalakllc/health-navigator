<?php

namespace Tests\Feature\Api\V1;

use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_published_specialties_ordered(): void
    {
        $specZebra = Specialty::factory()->create([
            'name' => 'Zebra',
            'slug' => 'zebra',
            'sort_order' => 2,
            'is_published' => true,
        ]);
        $specAlpha = Specialty::factory()->create([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        Specialty::factory()->unpublished()->create([
            'name' => 'Hidden',
            'slug' => 'hidden',
        ]);

        $d1 = Doctor::factory()->create();
        $d2 = Doctor::factory()->create();
        $d1->specialties()->attach($specAlpha->id, ['is_primary' => true]);
        $d2->specialties()->attach($specAlpha->id, ['is_primary' => true]);

        $response = $this->getJson('/api/v1/specialties');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'alpha')
            ->assertJsonPath('data.1.slug', 'zebra')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.doctors_count', 2)
            ->assertJsonPath('data.1.doctors_count', 0);
    }

    public function test_shows_published_specialty_by_slug(): void
    {
        $specialty = Specialty::factory()->create([
            'slug' => 'kardiologija',
            'name' => 'Кардиологија',
            'description' => 'Срце и крвни садови.',
            'is_published' => true,
        ]);

        Doctor::factory()->create()->specialties()->attach($specialty->id, ['is_primary' => true]);

        $this->getJson('/api/v1/specialties/kardiologija')
            ->assertOk()
            ->assertJsonPath('data.slug', 'kardiologija')
            ->assertJsonPath('data.doctors_count', 1);

        Specialty::factory()->unpublished()->create(['slug' => 'hidden-spec']);

        $this->getJson('/api/v1/specialties/hidden-spec')->assertNotFound();
    }
}
