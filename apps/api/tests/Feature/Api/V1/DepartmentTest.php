<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_published_departments(): void
    {
        Department::factory()->create([
            'name' => 'Кардиологија',
            'slug' => 'kardiologija',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        Department::factory()->unpublished()->create([
            'name' => 'Hidden',
            'slug' => 'hidden',
        ]);

        $this->getJson('/api/v1/departments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'kardiologija')
            ->assertJsonPath('data.0.name', 'Кардиологија');
    }
}
