<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /urgent-care and /urgent-care/cities („Каде веднаш“, docs/urgent-care.md).
 */
class UrgentCareTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_published_urgent_places_emergency_departments_first(): void
    {
        $ems = Facility::factory()->create(['name' => 'А Здравствен дом', 'city' => 'Битола', 'type' => FacilityType::Clinic, 'has_emergency_medical_service' => true]);
        $ed = Facility::factory()->create(['name' => 'Б Клиничка болница', 'city' => 'Битола', 'type' => FacilityType::Hospital, 'has_emergency_services' => true, 'is_open_24h' => true, 'urgent_care_evidence' => ['items' => [['text' => 'internal']]]]);
        Facility::factory()->create(['name' => 'В Клиника', 'city' => 'Битола', 'type' => FacilityType::Clinic]);
        Facility::factory()->unpublished()->create(['city' => 'Битола', 'has_emergency_services' => true]);
        Facility::factory()->pharmacy()->create(['city' => 'Битола', 'has_emergency_services' => true]);
        Facility::factory()->create(['city' => 'Охрид', 'has_emergency_services' => true]);

        $response = $this->getJson('/api/v1/urgent-care?'.http_build_query(['city' => 'Битола']))->assertOk();

        $this->assertSame([$ed->slug, $ems->slug], array_column($response->json('data'), 'slug'));
        $response->assertJsonPath('data.0.services', ['ed'])
            ->assertJsonPath('data.0.is_open_24h', true)
            ->assertJsonPath('data.0.hours_confirmed', true)
            ->assertJsonPath('data.1.services', ['ems'])
            ->assertJsonPath('data.1.emergency_hours', null)
            ->assertJsonPath('data.1.hours_confirmed', false)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.on_duty_pharmacies.available', false);
        $this->assertStringNotContainsString('internal', (string) $response->getContent(), 'The import evidence stays internal.');
        $this->assertArrayNotHasKey('urgent_care_evidence', $response->json('data.0'));
    }

    public function test_it_filters_by_service_and_sends_staff_hours(): void
    {
        $dental = Facility::factory()->create(['city' => 'Тетово', 'has_dental_emergency' => true, 'emergency_hours' => ['Пон' => '07:00–20:00']]);
        Facility::factory()->create(['city' => 'Тетово', 'has_emergency_medical_service' => true]);

        $this->getJson('/api/v1/urgent-care?'.http_build_query(['city' => 'Тетово', 'type' => 'dental']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $dental->slug)
            ->assertJsonPath('data.0.emergency_hours', ['Пон' => '07:00–20:00'])
            ->assertJsonPath('data.0.hours_confirmed', true)
            ->assertJsonPath('meta.type', 'dental');

        $this->getJson('/api/v1/urgent-care?type=pharmacy')->assertUnprocessable();
    }

    public function test_the_city_filter_matches_skopje_municipalities_and_latin(): void
    {
        $karpos = Facility::factory()->create(['city' => 'Скопје - Карпош', 'has_emergency_services' => true]);

        $this->getJson('/api/v1/urgent-care?'.http_build_query(['city' => 'Скопје']))->assertJsonPath('data.0.slug', $karpos->slug);
        $this->getJson('/api/v1/urgent-care?city=Skopje')->assertJsonPath('data.0.slug', $karpos->slug);
    }

    public function test_cities_count_services_and_merge_skopje_municipalities(): void
    {
        Facility::factory()->create(['city' => 'Скопје - Карпош', 'has_emergency_services' => true]);
        Facility::factory()->create(['city' => 'Скопје - Центар', 'has_emergency_medical_service' => true, 'has_dental_emergency' => true]);
        Facility::factory()->create(['city' => 'Битола', 'has_emergency_services' => true]);
        Facility::factory()->create(['city' => 'Прилеп']);
        Facility::factory()->unpublished()->create(['city' => 'Охрид', 'has_emergency_services' => true]);

        $this->getJson('/api/v1/urgent-care/cities')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['name' => 'Скопје', 'total' => 2, 'ed' => 1, 'ems' => 1, 'clinic' => 0, 'dental' => 1],
                ['name' => 'Битола', 'total' => 1, 'ed' => 1, 'ems' => 0, 'clinic' => 0, 'dental' => 0],
            ]]);
    }
}
