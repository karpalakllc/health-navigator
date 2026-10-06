<?php

namespace Tests\Feature\Api\V1;

use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSpecialtiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_search_returns_matching_published_specialties_in_either_script(): void
    {
        $cardio = Specialty::factory()->create(['slug' => 'kardiologija', 'name' => 'Кардиологија']);
        Specialty::factory()->create(['slug' => 'detska-kardiologija', 'name' => 'Детска кардиологија']);
        Specialty::factory()->unpublished()->create(['slug' => 'skriena', 'name' => 'Кардиологија скриена']);
        Specialty::factory()->create(['slug' => 'dermatologija', 'name' => 'Дерматологија']);

        foreach (Doctor::factory()->count(2)->create(['is_published' => true]) as $doctor) {
            $doctor->specialties()->attach($cardio->id, ['is_primary' => true]);
        }
        Doctor::factory()->unpublished()->create()->specialties()->attach($cardio->id, ['is_primary' => true]);

        $this->getJson('/api/v1/search?q=kardiolog')
            ->assertOk()
            ->assertJsonPath('data.specialties', [
                ['slug' => 'kardiologija', 'name' => 'Кардиологија', 'doctors_count' => 2],
                ['slug' => 'detska-kardiologija', 'name' => 'Детска кардиологија', 'doctors_count' => 0],
            ])
            // Shortcuts, not results: the matching doctors are counted already.
            ->assertJsonPath('data.grand_total', 2);
    }

    public function test_no_query_means_no_specialties(): void
    {
        Specialty::factory()->create(['name' => 'Кардиологија']);

        $this->getJson('/api/v1/search')
            ->assertOk()
            ->assertJsonPath('data.specialties', []);
    }
}
