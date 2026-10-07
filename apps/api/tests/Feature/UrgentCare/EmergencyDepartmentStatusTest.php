<?php

namespace Tests\Feature\UrgentCare;

use App\Enums\FacilityType;
use App\Enums\UserKind;
use App\Filament\Resources\Facilities\Pages\EditFacility;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\User;
use App\Support\UrgentCare\UrgentCareDeriver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Public general and clinical hospitals show as „итно одделение
 * (непотврдено)“ until staff decide (owner decision 2026-10-07,
 * docs/urgent-care.md § Data).
 */
class EmergencyDepartmentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function hospital(string $name, array $attributes = []): Facility
    {
        return Facility::factory()->create([
            'name' => $name,
            'type' => FacilityType::Hospital,
            'city' => 'Струмица',
            'phone' => '034 000 000',
            ...$attributes,
        ]);
    }

    public function test_public_general_and_clinical_hospitals_become_likely(): void
    {
        $general = $this->hospital('ЈЗУ Општа болница Струмица');
        $clinical = $this->hospital('Клиничка болница Штип', ['ownership' => 'public']);
        $city = $this->hospital('ЈЗУ Градска општа болница 8 Септември');
        $private = $this->hospital('ПЗУ Општа болница Приватна', ['ownership' => 'private']);
        $special = $this->hospital('ЈЗУ Специјална болница за ортопедија');
        $dentalCentre = $this->hospital('ЈЗУ Универзитетски стоматолошки клинички центар');

        $summary = app(UrgentCareDeriver::class)->run();

        $this->assertSame(Facility::ED_UNCONFIRMED_LIKELY, $general->refresh()->emergency_department_status);
        $this->assertFalse($general->has_emergency_services, 'Likely is not confirmed.');
        $this->assertSame(Facility::ED_UNCONFIRMED_LIKELY, $clinical->refresh()->emergency_department_status);
        $this->assertSame(Facility::ED_UNCONFIRMED_LIKELY, $city->refresh()->emergency_department_status);
        $this->assertNull($private->refresh()->emergency_department_status);
        $this->assertNull($special->refresh()->emergency_department_status);
        $this->assertNull($dentalCentre->refresh()->emergency_department_status);
        $this->assertSame(3, $summary['ed_likely_set']);
    }

    public function test_strong_evidence_confirms_and_staff_decisions_win(): void
    {
        $named = $this->hospital('ЈЗУ Општа болница Куманово');
        Doctor::factory()->create()->facilities()->attach($named->getKey(), ['work_unit' => 'Ургентни состојби', 'source' => 'fzom']);
        $none = $this->hospital('ЈЗУ Општа болница Гевгелија', ['emergency_department_status' => Facility::ED_NONE]);
        Doctor::factory()->create()->facilities()->attach($none->getKey(), ['work_unit' => 'Ургентен центар', 'source' => 'fzom']);
        $checked = $this->hospital('ЈЗУ Општа болница Кочани', ['urgent_care_checked_at' => now()]);

        app(UrgentCareDeriver::class)->run();

        $named->refresh();
        $this->assertSame(Facility::ED_CONFIRMED, $named->emergency_department_status);
        $this->assertTrue($named->has_emergency_services);
        $none->refresh();
        $this->assertSame(Facility::ED_NONE, $none->emergency_department_status, 'Staff said none: evidence does not override it.');
        $this->assertFalse($none->has_emergency_services);
        $this->assertNull($checked->refresh()->emergency_department_status, 'Confirmed by staff: left alone.');

        // Staff downgrade a likely one to none; the next run keeps it.
        $likely = $this->hospital('ЈЗУ Општа болница Тетово');
        app(UrgentCareDeriver::class)->run();
        $likely->refresh()->update(['emergency_department_status' => Facility::ED_NONE]);
        app(UrgentCareDeriver::class)->run();
        $this->assertSame(Facility::ED_NONE, $likely->refresh()->emergency_department_status);
    }

    public function test_the_flag_and_the_status_stay_in_step(): void
    {
        $facility = $this->hospital('Болница');

        $facility->update(['has_emergency_services' => true]);
        $this->assertSame(Facility::ED_CONFIRMED, $facility->refresh()->emergency_department_status);

        $facility->update(['emergency_department_status' => Facility::ED_NONE]);
        $this->assertFalse($facility->refresh()->has_emergency_services);

        $facility->update(['emergency_department_status' => Facility::ED_CONFIRMED]);
        $this->assertTrue($facility->refresh()->has_emergency_services);

        $facility->update(['has_emergency_services' => false]);
        $this->assertNull($facility->refresh()->emergency_department_status);
    }

    public function test_the_legacy_flag_never_turns_a_staff_none_into_confirmed(): void
    {
        $facility = $this->hospital('Болница', ['emergency_department_status' => Facility::ED_NONE]);

        $facility->has_emergency_services = true;
        $facility->save();

        $facility->refresh();
        $this->assertSame(Facility::ED_NONE, $facility->emergency_department_status);
        $this->assertFalse($facility->has_emergency_services);
    }

    public function test_the_finder_lists_likely_ones_after_confirmed_ones_with_their_status(): void
    {
        $likely = $this->hospital('А Општа болница', ['emergency_department_status' => Facility::ED_UNCONFIRMED_LIKELY]);
        $confirmed = $this->hospital('Б Клиничка болница', ['emergency_department_status' => Facility::ED_CONFIRMED]);
        $ems = $this->hospital('В Здравствен дом', ['type' => FacilityType::Clinic, 'has_emergency_medical_service' => true]);
        $this->hospital('Г Болница без одделение', ['emergency_department_status' => Facility::ED_NONE]);

        $response = $this->getJson('/api/v1/urgent-care?'.http_build_query(['city' => 'Струмица']))->assertOk();

        $this->assertSame([$confirmed->slug, $likely->slug, $ems->slug], array_column($response->json('data'), 'slug'));
        $response->assertJsonPath('data.0.ed_status', 'confirmed')
            ->assertJsonPath('data.1.ed_status', 'unconfirmed_likely')
            ->assertJsonPath('data.1.services', ['ed'])
            ->assertJsonPath('data.1.phone', '034 000 000')
            ->assertJsonPath('data.2.ed_status', null);

        $this->getJson('/api/v1/urgent-care?'.http_build_query(['city' => 'Струмица', 'type' => 'ed']))
            ->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/urgent-care/cities')
            ->assertJsonPath('data.0.ed', 2);
    }

    public function test_staff_confirm_or_set_none_in_the_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = User::factory()->create(['user_kind' => UserKind::Staff]);
        $admin->syncRoles(['Administrator']);
        $facility = $this->hospital('ЈЗУ Општа болница Велес', ['emergency_department_status' => Facility::ED_UNCONFIRMED_LIKELY]);

        $this->actingAs($admin);
        Livewire::test(EditFacility::class, ['record' => $facility->getRouteKey()])
            ->fillForm(['emergency_department_status' => Facility::ED_CONFIRMED])
            ->call('save')
            ->assertHasNoFormErrors();

        $facility->refresh();
        $this->assertSame(Facility::ED_CONFIRMED, $facility->emergency_department_status);
        $this->assertTrue($facility->has_emergency_services);
    }
}
