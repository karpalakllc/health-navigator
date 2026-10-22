<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\SiteSetting;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Typo-tolerant fallback of the SQL search path (FuzzyNameSearch): when the
 * exact, script-insensitive search finds nothing, names close to the query
 * are returned instead, marked fuzzy, with a „Дали мислевте“ suggestion.
 */
class FuzzySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_latin_typo_finds_the_cyrillic_doctor_with_a_suggestion(): void
    {
        Doctor::factory()->create(['slug' => 'dominika', 'full_name' => 'Доминика Славкоска', 'city' => 'Скопје']);
        Doctor::factory()->create(['slug' => 'marko', 'full_name' => 'Марко Петровски', 'city' => 'Скопје']);

        $this->getJson('/api/v1/search?q=domenika')
            ->assertOk()
            ->assertJsonPath('data.fuzzy', true)
            ->assertJsonPath('data.suggestion', 'Доминика')
            ->assertJsonPath('data.doctors.meta.total', 1)
            ->assertJsonPath('data.doctors.data.0.slug', 'dominika')
            ->assertJsonPath('data.grand_total', 1);
    }

    public function test_an_exact_search_is_not_fuzzy(): void
    {
        Doctor::factory()->create(['slug' => 'dominika', 'full_name' => 'Доминика Славкоска']);

        $this->getJson('/api/v1/search?q=dominika')
            ->assertOk()
            ->assertJsonPath('data.fuzzy', false)
            ->assertJsonPath('data.suggestion', null)
            ->assertJsonPath('data.doctors.meta.total', 1);
    }

    public function test_surname_typo_ranks_the_closest_names_and_leaves_out_the_rest(): void
    {
        Doctor::factory()->create(['slug' => 'zoran', 'full_name' => 'Зоран Божиновски']);
        Doctor::factory()->create(['slug' => 'jovan', 'full_name' => 'Јован Божиновски']);
        Doctor::factory()->create(['slug' => 'ana', 'full_name' => 'Ана Трајковска']);
        Doctor::factory()->create(['slug' => 'ivan', 'full_name' => 'Иван Стојановски']);

        $response = $this->getJson('/api/v1/search?q=bozinovski')
            ->assertOk()
            ->assertJsonPath('data.fuzzy', true)
            ->assertJsonPath('data.suggestion', 'Божиновски')
            ->assertJsonPath('data.doctors.meta.total', 2);

        $this->assertEqualsCanonicalizing(['zoran', 'jovan'], array_column($response->json('data.doctors.data'), 'slug'));
    }

    public function test_unrelated_queries_find_nothing(): void
    {
        Doctor::factory()->create(['full_name' => 'Доминика Славкоска']);
        Doctor::factory()->create(['full_name' => 'Зоран Божиновски']);
        Facility::factory()->create(['name' => 'ПЗУ Кардиоцентар', 'type' => FacilityType::Clinic]);
        Specialty::factory()->create(['name' => 'Кардиологија', 'slug' => 'kardiologija']);

        foreach (['qwertyu', 'асдфгх', 'xyzzy'] as $junk) {
            $this->getJson('/api/v1/search?q='.urlencode($junk))
                ->assertOk()
                ->assertJsonPath('data.fuzzy', false)
                ->assertJsonPath('data.suggestion', null)
                ->assertJsonPath('data.grand_total', 0)
                ->assertJsonPath('data.specialties', []);
        }
    }

    public function test_short_queries_are_never_fuzzy(): void
    {
        Doctor::factory()->create(['full_name' => 'Ана Трајковска']);

        $this->getJson('/api/v1/search?q=anx')
            ->assertOk()
            ->assertJsonPath('data.fuzzy', false)
            ->assertJsonPath('data.grand_total', 0);
    }

    public function test_only_published_rows_are_fuzzy_matches(): void
    {
        Doctor::factory()->unpublished()->create(['full_name' => 'Доминика Славкоска']);
        Facility::factory()->unpublished()->create(['name' => 'ПЗУ Доминика', 'type' => FacilityType::Clinic]);

        $this->getJson('/api/v1/search?q=domenika')
            ->assertOk()
            ->assertJsonPath('data.fuzzy', false)
            ->assertJsonPath('data.grand_total', 0);
    }

    public function test_the_city_filter_still_applies(): void
    {
        Doctor::factory()->create(['slug' => 'skopje', 'full_name' => 'Доминика Славкоска', 'city' => 'Скопје']);
        Doctor::factory()->create(['slug' => 'bitola', 'full_name' => 'Доминика Јанева', 'city' => 'Битола']);

        $this->getJson('/api/v1/search?q=domenika&city=Bitola')
            ->assertOk()
            ->assertJsonPath('data.fuzzy', true)
            ->assertJsonPath('data.doctors.meta.total', 1)
            ->assertJsonPath('data.doctors.data.0.slug', 'bitola');

        $this->getJson('/api/v1/search?q=domenika&city='.urlencode('Охрид'))
            ->assertOk()
            ->assertJsonPath('data.fuzzy', false)
            ->assertJsonPath('data.grand_total', 0);
    }

    public function test_facilities_and_pharmacies_respect_their_verticals_and_the_module_switch(): void
    {
        Facility::factory()->create(['slug' => 'sistina', 'name' => 'ПЗУ Аџибадем Систина', 'type' => FacilityType::Hospital]);
        Facility::factory()->create(['slug' => 'sistina-pharmacy', 'name' => 'Аптека Систина', 'type' => FacilityType::Pharmacy]);
        SiteSetting::current()->update(['public_pharmacies' => true]);

        $this->getJson('/api/v1/search?q=sistna')
            ->assertOk()
            ->assertJsonPath('data.fuzzy', true)
            ->assertJsonPath('data.suggestion', 'Систина')
            ->assertJsonPath('data.facilities.data.0.slug', 'sistina')
            ->assertJsonPath('data.facilities.meta.total', 1)
            ->assertJsonPath('data.pharmacies.data.0.slug', 'sistina-pharmacy')
            ->assertJsonPath('data.pharmacies.meta.total', 1);

        SiteSetting::current()->update(['public_pharmacies' => false]);

        $this->getJson('/api/v1/search?q=sistna')
            ->assertOk()
            ->assertJsonPath('data.pharmacies.meta.total', 0);
    }

    public function test_a_misspelt_specialty_returns_its_chip_and_doctors(): void
    {
        $cardiology = Specialty::factory()->create(['name' => 'Кардиологија', 'slug' => 'kardiologija']);
        Specialty::factory()->create(['name' => 'Дерматологија', 'slug' => 'dermatologija']);
        Specialty::factory()->unpublished()->create(['name' => 'Кардиохирургија', 'slug' => 'kardiohirurgija']);
        $doctor = Doctor::factory()->create(['slug' => 'cardiologist', 'full_name' => 'Петар Петров']);
        $doctor->specialties()->attach($cardiology->id, ['is_primary' => true]);

        $this->getJson('/api/v1/search?q=kardolog')
            ->assertOk()
            ->assertJsonPath('data.fuzzy', true)
            ->assertJsonPath('data.suggestion', 'Кардиологија')
            ->assertJsonCount(1, 'data.specialties')
            ->assertJsonPath('data.specialties.0.slug', 'kardiologija')
            ->assertJsonPath('data.specialties.0.doctors_count', 1)
            ->assertJsonPath('data.doctors.data.0.slug', 'cardiologist');
    }

    public function test_the_doctor_list_falls_back_to_fuzzy_names_under_its_filters(): void
    {
        $specialty = Specialty::factory()->create(['name' => 'Педијатрија', 'slug' => 'pedijatrija']);
        $match = Doctor::factory()->create(['slug' => 'match', 'full_name' => 'Доминика Славкоска']);
        $match->specialties()->attach($specialty->id, ['is_primary' => true]);
        Doctor::factory()->create(['slug' => 'other-specialty', 'full_name' => 'Доминика Јанева']);

        $this->getJson('/api/v1/doctors?q=domenika&specialty=pedijatrija')
            ->assertOk()
            ->assertJsonPath('meta.fuzzy', true)
            ->assertJsonPath('meta.suggestion', 'Доминика')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'match');

        $this->getJson('/api/v1/doctors?q=dominika')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonMissingPath('meta.fuzzy');

        $this->getJson('/api/v1/doctors?q=qwertyu')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.fuzzy', false)
            ->assertJsonPath('meta.suggestion', null);
    }

    public function test_the_facility_and_pharmacy_lists_fall_back_to_fuzzy_names(): void
    {
        Facility::factory()->create(['slug' => 'sistina', 'name' => 'ПЗУ Аџибадем Систина', 'type' => FacilityType::Hospital]);
        Facility::factory()->create(['slug' => 'other', 'name' => 'ЈЗУ Здравствен дом', 'type' => FacilityType::Clinic]);
        Facility::factory()->create(['slug' => 'apteka', 'name' => 'Аптека Зегин', 'type' => FacilityType::Pharmacy]);
        SiteSetting::current()->update(['public_pharmacies' => true]);

        $this->getJson('/api/v1/facilities?q=sistna')
            ->assertOk()
            ->assertJsonPath('meta.fuzzy', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'sistina');

        $this->getJson('/api/v1/pharmacies?q=zegim')
            ->assertOk()
            ->assertJsonPath('meta.fuzzy', true)
            ->assertJsonPath('meta.suggestion', 'Зегин')
            ->assertJsonPath('data.0.slug', 'apteka');
    }
}
