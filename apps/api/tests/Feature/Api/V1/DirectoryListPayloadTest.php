<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Models\Doctor;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The directory cards render a tap-to-call „Јави се“ and an open-now line when
 * the list item carries phone and office_hours, so the three list payloads
 * (and the unified search, which reuses them) include both.
 */
class DirectoryListPayloadTest extends TestCase
{
    use RefreshDatabase;

    private const HOURS = ['Пон–Пет' => '08:00–16:00', 'Саб' => '09:00–13:00'];

    public function test_doctor_list_items_carry_phone_and_office_hours(): void
    {
        Doctor::factory()->create([
            'full_name' => 'д-р Ана',
            'phone' => '+389 2 123 456',
            'office_hours' => self::HOURS,
        ]);

        $this->getJson('/api/v1/doctors')
            ->assertOk()
            ->assertJsonPath('data.0.phone', '+389 2 123 456')
            ->assertJsonPath('data.0.office_hours', self::HOURS);
    }

    public function test_facility_list_items_carry_phone_and_office_hours(): void
    {
        Facility::factory()->create([
            'type' => FacilityType::Clinic,
            'phone' => '+389 2 222 333',
            'office_hours' => self::HOURS,
        ]);

        $this->getJson('/api/v1/facilities')
            ->assertOk()
            ->assertJsonPath('data.0.phone', '+389 2 222 333')
            ->assertJsonPath('data.0.office_hours', self::HOURS);
    }

    public function test_pharmacy_list_items_carry_phone_and_office_hours(): void
    {
        Facility::factory()->pharmacy()->create([
            'phone' => '+389 2 444 555',
            'office_hours' => self::HOURS,
        ]);

        $this->getJson('/api/v1/pharmacies')
            ->assertOk()
            ->assertJsonPath('data.0.phone', '+389 2 444 555')
            ->assertJsonPath('data.0.office_hours', self::HOURS);
    }

    public function test_missing_contact_data_is_null_phone_and_empty_hours(): void
    {
        Doctor::factory()->create(['phone' => null, 'office_hours' => null]);
        Facility::factory()->create(['type' => FacilityType::Clinic, 'phone' => null, 'office_hours' => null]);
        Facility::factory()->pharmacy()->create(['phone' => null, 'office_hours' => null]);

        foreach (['/api/v1/doctors', '/api/v1/facilities', '/api/v1/pharmacies'] as $uri) {
            $this->getJson($uri)
                ->assertOk()
                ->assertJsonPath('data.0.phone', null)
                ->assertJsonPath('data.0.office_hours', []);
        }
    }

    public function test_unified_search_sections_carry_the_same_fields(): void
    {
        Doctor::factory()->create([
            'full_name' => 'д-р Бојан Тест',
            'phone' => '+389 2 123 456',
            'office_hours' => self::HOURS,
        ]);

        $this->getJson('/api/v1/search?q='.rawurlencode('Бојан'))
            ->assertOk()
            ->assertJsonPath('data.doctors.data.0.phone', '+389 2 123 456')
            ->assertJsonPath('data.doctors.data.0.office_hours', self::HOURS);
    }
}
