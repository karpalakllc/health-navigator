<?php

namespace Tests\Feature\Filament;

use App\Enums\UserKind;
use App\Filament\Resources\Pharmacies\Pages\EditPharmacy;
use App\Models\Facility;
use App\Models\ImportRun;
use App\Models\PharmacyDutyShift;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Import\Pharmacies\OnDutyNames;
use App\Support\Import\Pharmacies\OnDutyPharmacyImporter;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PharmacyUnlinkActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_unlink_the_on_duty_schedule_from_the_pharmacy(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        $admin = User::factory()->create(['user_kind' => UserKind::Staff, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $admin->syncRoles([RoleCatalog::ADMINISTRATOR]);
        $this->actingAs($admin);

        $pharmacy = Facility::factory()->pharmacy()->create(['name' => 'Аптека Роса', 'city' => 'Велес']);
        $other = Facility::factory()->pharmacy()->create(['name' => 'Аптека Друга', 'city' => 'Велес']);
        $alone = Facility::factory()->pharmacy()->create(['name' => 'Аптека Сама', 'city' => 'Битола']);
        $run = ImportRun::start(OnDutyPharmacyImporter::SOURCE, false, null);
        $shift = PharmacyDutyShift::query()->create([
            'month' => '2026-10', 'duty_date' => '2026-10-07', 'town' => 'Велес', 'town_key' => OnDutyNames::townKey('Велес'),
            'pharmacy_name' => 'Роса', 'name_key' => OnDutyNames::pharmacyKey('Роса', 'Велес'), 'mode' => 'hours',
            'import_run_id' => $run->getKey(), 'facility_id' => $pharmacy->id,
        ]);

        Livewire::test(EditPharmacy::class, ['record' => $alone->getRouteKey()])->assertActionHidden('unlinkOnDuty');

        Livewire::test(EditPharmacy::class, ['record' => $pharmacy->getRouteKey()])
            ->assertActionVisible('unlinkOnDuty')
            ->callAction('unlinkOnDuty', ['schedule' => $shift->town_key.'|'.$shift->name_key, 'link_to' => $other->id]);

        $this->assertSame($other->id, $shift->fresh()->facility_id);
    }

    public function test_a_moderator_without_import_rights_cannot_unlink_even_by_calling_the_action(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        $moderator = User::factory()->create(['user_kind' => UserKind::Staff, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $moderator->syncRoles([RoleCatalog::ADMINISTRATOR]);
        $moderator->roles->first()->revokePermissionTo('imports.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $moderator = $moderator->fresh();
        $this->assertFalse($moderator->can('imports.manage'));
        $this->actingAs($moderator);

        $pharmacy = Facility::factory()->pharmacy()->create(['name' => 'Аптека Роса', 'city' => 'Велес']);
        $run = ImportRun::start(OnDutyPharmacyImporter::SOURCE, false, null);
        $shift = PharmacyDutyShift::query()->create([
            'month' => '2026-10', 'duty_date' => '2026-10-07', 'town' => 'Велес', 'town_key' => OnDutyNames::townKey('Велес'),
            'pharmacy_name' => 'Роса', 'name_key' => OnDutyNames::pharmacyKey('Роса', 'Велес'), 'mode' => 'hours',
            'import_run_id' => $run->getKey(), 'facility_id' => $pharmacy->id,
        ]);

        Livewire::test(EditPharmacy::class, ['record' => $pharmacy->getRouteKey()])
            ->assertActionHidden('unlinkOnDuty')
            ->call('mountAction', 'unlinkOnDuty')
            ->call('callMountedAction');

        $this->assertSame($pharmacy->id, $shift->fresh()->facility_id);
    }
}
