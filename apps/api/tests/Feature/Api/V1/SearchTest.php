<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ForumTopic;
use App\Models\Product;
use App\Models\Specialty;
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

    public function test_unified_search_finds_doctors_and_facilities_by_specialty_or_department(): void
    {
        $cardiology = Specialty::factory()->create(['name' => 'Кардиологија', 'slug' => 'kardiologija']);
        Doctor::factory()->create(['slug' => 'cardio-doc', 'full_name' => 'д-р Ана Петровска'])
            ->specialties()->attach($cardiology->id, ['is_primary' => true]);
        Doctor::factory()->create(['slug' => 'other-doc', 'full_name' => 'д-р Марко Стојанов']);
        Facility::factory()->create(['slug' => 'city-hospital', 'name' => 'Градска болница', 'type' => FacilityType::Hospital])
            ->departments()->attach(Department::factory()->create(['name' => 'Кардиологија', 'slug' => 'kardiologija'])->id);

        foreach (['кардио', 'kardio'] as $q) {
            $this->getJson('/api/v1/search?q='.urlencode($q))
                ->assertOk()
                ->assertJsonPath('data.doctors.meta.total', 1)
                ->assertJsonPath('data.doctors.data.0.slug', 'cardio-doc')
                ->assertJsonPath('data.facilities.meta.total', 1)
                ->assertJsonPath('data.facilities.data.0.slug', 'city-hospital');
        }
    }
}
