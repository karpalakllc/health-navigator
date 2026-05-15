<?php

namespace Tests\Feature\Api\V1;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_published_doctors_with_pagination_meta(): void
    {
        Doctor::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/doctors?per_page=2');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['slug', 'full_name', 'title', 'city', 'primary_specialty'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_excludes_unpublished_doctors(): void
    {
        Doctor::factory()->create(['slug' => 'published-doc']);
        Doctor::factory()->unpublished()->create(['slug' => 'hidden-doc']);

        $this->getJson('/api/v1/doctors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'published-doc');
    }

    public function test_filters_by_specialty_slug(): void
    {
        $cardiology = Specialty::factory()->create(['slug' => 'kardiologija']);
        $pediatrics = Specialty::factory()->create(['slug' => 'pedijatrija']);

        $cardiologist = Doctor::factory()->create(['slug' => 'cardio-doc']);
        $cardiologist->specialties()->attach($cardiology->id, ['is_primary' => true]);

        $pediatrician = Doctor::factory()->create(['slug' => 'ped-doc']);
        $pediatrician->specialties()->attach($pediatrics->id, ['is_primary' => true]);

        $this->getJson('/api/v1/doctors?specialty=kardiologija')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'cardio-doc');
    }

    public function test_filters_by_city_case_insensitively(): void
    {
        Doctor::factory()->create(['slug' => 'skopje-doc', 'city' => 'Skopje']);
        Doctor::factory()->create(['slug' => 'bitola-doc', 'city' => 'Bitola']);

        $this->getJson('/api/v1/doctors?city=SKOPJE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'skopje-doc');
    }

    public function test_filters_by_name_query(): void
    {
        Doctor::factory()->create(['slug' => 'ana', 'full_name' => 'Dr Ana Petrovska']);
        Doctor::factory()->create(['slug' => 'marko', 'full_name' => 'Dr Marko Stojanov']);

        $this->getJson('/api/v1/doctors?q=ana')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'ana');
    }

    public function test_shows_published_doctor_detail(): void
    {
        $specialty = Specialty::factory()->create([
            'slug' => 'kardiologija',
            'name' => 'Кардиологија',
        ]);

        $doctor = Doctor::factory()->create([
            'slug' => 'ana-petrovska',
            'full_name' => 'д-р Ана Петровска',
            'bio' => 'Пример био.',
            'city' => 'Скопје',
        ]);
        $doctor->specialties()->attach($specialty->id, ['is_primary' => true]);

        $this->getJson('/api/v1/doctors/ana-petrovska')
            ->assertOk()
            ->assertJsonPath('data.slug', 'ana-petrovska')
            ->assertJsonPath('data.specialties.0.slug', 'kardiologija')
            ->assertJsonPath('data.specialties.0.is_primary', true);
    }

    public function test_detail_includes_published_clinical_facilities(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'affiliated-doc']);
        $clinic = Facility::factory()->create([
            'slug' => 'city-clinic',
            'name' => 'City Clinic',
            'type' => 'clinic',
        ]);
        $pharmacy = Facility::factory()->pharmacy()->create(['slug' => 'main-pharmacy']);

        $doctor->facilities()->attach($clinic->id, ['is_primary' => true]);
        $doctor->facilities()->attach($pharmacy->id, ['is_primary' => false]);

        $this->getJson('/api/v1/doctors/affiliated-doc')
            ->assertOk()
            ->assertJsonCount(1, 'data.facilities')
            ->assertJsonPath('data.facilities.0.slug', 'city-clinic')
            ->assertJsonPath('data.facilities.0.is_primary', true);
    }

    public function test_detail_includes_extended_profile_fields(): void
    {
        Doctor::factory()->create([
            'slug' => 'profile-doc',
            'subspecialty' => 'Интервенционална кардиологија',
            'years_experience' => 12,
            'education' => 'УКИМ',
            'languages' => ['Македонски', 'Англиски'],
            'clinical_interests' => ['Хипертензија'],
            'procedures' => ['Ехокардиографија'],
            'consultation_fee_note' => '2.500 МКД',
            'office_hours' => ['Пон' => '08:00–14:00'],
            'accepts_new_patients' => true,
            'is_featured' => true,
        ]);

        $this->getJson('/api/v1/doctors/profile-doc')
            ->assertOk()
            ->assertJsonPath('data.subspecialty', 'Интервенционална кардиологија')
            ->assertJsonPath('data.years_experience', 12)
            ->assertJsonPath('data.languages.0', 'Македонски')
            ->assertJsonPath('data.office_hours.Пон', '08:00–14:00')
            ->assertJsonPath('data.is_featured', true);
    }

    public function test_filters_featured_doctors(): void
    {
        Doctor::factory()->create(['slug' => 'featured-doc', 'is_featured' => true]);
        Doctor::factory()->create(['slug' => 'regular-doc', 'is_featured' => false]);

        $this->getJson('/api/v1/doctors?featured=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'featured-doc');
    }

    public function test_detail_returns_404_for_unpublished_doctor(): void
    {
        Doctor::factory()->unpublished()->create(['slug' => 'hidden-doc']);

        $this->getJson('/api/v1/doctors/hidden-doc')->assertNotFound();
    }

    public function test_validates_list_query_parameters(): void
    {
        $this->getJson('/api/v1/doctors?per_page=100')->assertUnprocessable();
    }
}
