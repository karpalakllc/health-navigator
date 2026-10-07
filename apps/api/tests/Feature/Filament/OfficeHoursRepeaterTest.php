<?php

namespace Tests\Feature\Filament;

use App\Enums\FacilityType;
use App\Enums\UserKind;
use App\Filament\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Resources\Facilities\Pages\EditFacility;
use App\Filament\Resources\Pharmacies\Pages\EditPharmacy;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The weekly-hours repeaters store {day: hours}. Saving the form must keep
 * the day labels (they were dropped: the repeater re-keyed the dehydrated map
 * into a list, so „Пон“ → 0).
 */
class OfficeHoursRepeaterTest extends TestCase
{
    use RefreshDatabase;

    private const ROWS = [
        'row-1' => ['day' => 'Пон', 'hours' => '08:00–14:00'],
        'row-2' => ['day' => 'Саб', 'hours' => '09:00–12:00'],
    ];

    private const HOURS = ['Пон' => '08:00–14:00', 'Саб' => '09:00–12:00'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::factory()->create(['user_kind' => UserKind::Staff]);
        $admin->syncRoles(['Administrator']);
        $this->actingAs($admin);
    }

    public function test_a_doctors_office_hours_keep_their_days(): void
    {
        $doctor = Doctor::factory()->create(['office_hours' => ['Вто' => '10:00–12:00']]);

        Livewire::test(EditDoctor::class, ['record' => $doctor->getRouteKey()])
            ->fillForm(['office_hours' => self::ROWS])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(self::HOURS, $doctor->refresh()->office_hours);
    }

    public function test_a_facilitys_opening_hours_keep_their_days(): void
    {
        $facility = Facility::factory()->create(['type' => FacilityType::Clinic]);

        Livewire::test(EditFacility::class, ['record' => $facility->getRouteKey()])
            ->fillForm(['office_hours' => self::ROWS])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(self::HOURS, $facility->refresh()->office_hours);
    }

    public function test_a_pharmacys_opening_hours_keep_their_days(): void
    {
        $pharmacy = Facility::factory()->pharmacy()->create();

        Livewire::test(EditPharmacy::class, ['record' => $pharmacy->getRouteKey()])
            ->fillForm(['office_hours' => self::ROWS])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(self::HOURS, $pharmacy->refresh()->office_hours);
    }

    public function test_stored_hours_load_back_into_the_form_and_survive_an_unrelated_save(): void
    {
        $facility = Facility::factory()->create(['type' => FacilityType::Clinic, 'office_hours' => self::HOURS]);

        Livewire::test(EditFacility::class, ['record' => $facility->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(self::HOURS, $facility->refresh()->office_hours);
    }
}
