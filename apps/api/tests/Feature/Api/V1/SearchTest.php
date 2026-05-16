<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTopic;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_search_returns_grouped_results(): void
    {
        Doctor::factory()->create(['slug' => 'ana-doc', 'full_name' => 'Ana Petrovska']);
        Facility::factory()->create([
            'slug' => 'ana-clinic',
            'name' => 'Ana Clinic',
            'type' => FacilityType::Clinic,
        ]);
        Facility::factory()->create([
            'slug' => 'ana-pharmacy',
            'name' => 'Ana Pharmacy',
            'type' => FacilityType::Pharmacy,
        ]);
        Product::factory()->create(['slug' => 'ana-product', 'name' => 'Ana Product']);

        $this->getJson('/api/v1/search?q=ana&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.grand_total', 4)
            ->assertJsonCount(1, 'data.doctors.data')
            ->assertJsonCount(1, 'data.facilities.data')
            ->assertJsonCount(1, 'data.pharmacies.data')
            ->assertJsonCount(1, 'data.products.data')
            ->assertJsonStructure([
                'data' => [
                    'doctors' => ['data', 'meta' => ['total']],
                    'facilities' => ['data', 'meta' => ['total']],
                    'pharmacies' => ['data', 'meta' => ['total']],
                    'products' => ['data', 'meta' => ['total']],
                    'forum_topics' => ['data', 'meta' => ['total']],
                    'grand_total',
                ],
            ]);
    }

    public function test_unified_search_includes_forum_topics_when_module_enabled(): void
    {
        ForumTopic::factory()->create(['title' => 'Ana sleep hygiene tips']);

        $this->getJson('/api/v1/search?q=ana&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.forum_topics.meta.total', 1)
            ->assertJsonPath('data.forum_topics.data.0.title', 'Ana sleep hygiene tips');
    }

    public function test_unified_search_without_query_returns_empty_sections(): void
    {
        Doctor::factory()->create();

        $this->getJson('/api/v1/search')
            ->assertOk()
            ->assertJsonPath('data.grand_total', 0)
            ->assertJsonCount(0, 'data.doctors.data');
    }

    public function test_unified_search_respects_per_page_max(): void
    {
        Doctor::factory()->count(12)->create(['full_name' => 'Ana Test']);

        $this->getJson('/api/v1/search?q=ana&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.doctors.meta.per_page', 10)
            ->assertJsonCount(10, 'data.doctors.data');

        $this->getJson('/api/v1/search?q=ana&per_page=11')->assertUnprocessable();
    }
}
