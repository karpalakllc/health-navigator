<?php

namespace Tests\Feature\Filament;

use App\Enums\FacilityType;
use App\Enums\ImportReviewStatus;
use App\Enums\UserKind;
use App\Filament\Resources\Facilities\Pages\EditFacility;
use App\Filament\Resources\ImportReviewItems\Pages\ListImportReviewItems;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ImportReviewItem;
use App\Models\SiteSetting;
use App\Models\SourceRecord;
use App\Models\User;
use App\Support\Import\Facilities\FacilityDuplicatePlanner;
use App\Support\Import\Facilities\FacilityMerger;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * „Merge into register facility…“ in the admin: on the facility edit page
 * and on the „Same facility?“ review items (one, or the suggested one of
 * each selected item).
 */
class FacilityMergeActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        FacilityMerger::forgetSchema();

        $admin = User::factory()->create(['user_kind' => UserKind::Staff, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $admin->syncRoles([RoleCatalog::ADMINISTRATOR]);
        $this->actingAs($admin);
    }

    private function register(string $name, string $city): Facility
    {
        $facility = Facility::factory()->create(['name' => $name, 'city' => $city, 'address' => 'Ул. Тестна 1', 'type' => FacilityType::Clinic]);
        $facility->forceFill(['import_source' => 'fzom'])->save();
        SourceRecord::query()->create([
            'source' => 'fzom', 'external_key' => 'facility:zu:'.$facility->id, 'subject_type' => 'facility', 'subject_id' => $facility->id,
            'payload' => [], 'hash' => 'h', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        return $facility;
    }

    private function draft(string $name, string $city): Facility
    {
        $facility = Facility::factory()->unpublished()->create(['name' => $name, 'city' => $city, 'address' => 'Тестна 1', 'type' => FacilityType::Clinic]);
        $facility->forceFill(['import_source' => 'website'])->save();
        $doctor = Doctor::factory()->create();
        DB::table('doctor_facility')->insert(['doctor_id' => $doctor->id, 'facility_id' => $facility->id, 'is_primary' => true, 'source' => 'website', 'created_at' => now(), 'updated_at' => now()]);

        return $facility;
    }

    private function item(Facility $draft, Facility $suggested): ImportReviewItem
    {
        app(FacilityDuplicatePlanner::class)->queue([
            'facility_id' => $draft->id,
            'confidence' => 'medium',
            'reasons' => ['name: same name'],
            'doctors' => 1,
            'candidates' => [['facility_id' => $suggested->id, 'name' => $suggested->name, 'city' => $suggested->city, 'address' => $suggested->address, 'confidence' => 'medium', 'reasons' => ['name: same name']]],
        ]);

        return ImportReviewItem::query()->where('item_key', FacilityDuplicatePlanner::ITEM_PREFIX.$draft->id)->sole();
    }

    public function test_the_suggested_facilities_of_the_selected_items_are_merged(): void
    {
        $first = $this->register('ПЗУ Сара Дент', 'Тетово');
        $second = $this->register('ПЗУ Дуодент', 'Струмица');
        $one = $this->item($this->draft('Saradent', 'Тетово'), $first);
        $two = $this->item($this->draft('Duo Dent', 'Струмица'), $second);

        Livewire::test(ListImportReviewItems::class)
            ->callTableBulkAction('mergeSuggestedFacilities', [$one, $two]);

        $this->assertSame($first->id, Facility::withTrashed()->find($one->subject_id)?->merged_into_id);
        $this->assertSame($second->id, Facility::withTrashed()->find($two->subject_id)?->merged_into_id);
        $this->assertSame(ImportReviewStatus::Resolved, $one->fresh()->status);
        $this->assertSame(2, DB::table('doctor_facility')->whereIn('facility_id', [$first->id, $second->id])->count());
    }

    public function test_staff_pick_another_register_facility_on_the_item(): void
    {
        $suggested = $this->register('ПЗУ Сара Дент', 'Тетово');
        $chosen = $this->register('ПЗУ Сарадент 2000', 'Тетово');
        $item = $this->item($this->draft('Saradent', 'Тетово'), $suggested);

        Livewire::test(ListImportReviewItems::class)
            ->callTableAction('mergeIntoRegisterFacility', $item, ['facility_id' => $chosen->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame($chosen->id, Facility::withTrashed()->find($item->subject_id)?->merged_into_id);
        $this->assertSame('merged', $item->fresh()->resolution);
    }

    public function test_the_edit_page_merges_a_website_only_facility_and_not_a_register_one(): void
    {
        $register = $this->register('ПЗУ Попоски', 'Охрид');
        $draft = $this->draft('Попоски Дентал', 'Охрид');

        Livewire::test(EditFacility::class, ['record' => $register->getKey()])
            ->assertActionHidden('mergeIntoRegisterFacility');

        Livewire::test(EditFacility::class, ['record' => $draft->getKey()])
            ->assertActionVisible('mergeIntoRegisterFacility')
            ->callAction('mergeIntoRegisterFacility', ['facility_id' => $register->id])
            ->assertHasNoActionErrors();

        $this->assertTrue(Facility::withTrashed()->find($draft->id)?->trashed());
        $this->assertSame(1, DB::table('doctor_facility')->where('facility_id', $register->id)->count());
    }

    public function test_a_user_without_import_permission_cannot_merge(): void
    {
        $register = $this->register('ПЗУ Попоски', 'Охрид');
        $draft = $this->draft('Попоски Дентал', 'Охрид');
        $viewer = User::factory()->create(['user_kind' => UserKind::Staff, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $viewer->syncRoles([]);
        $viewer->givePermissionTo('imports.view');
        $this->actingAs($viewer);

        $this->assertSame(403, $this->tryMerge($draft, $register));
        $this->assertFalse($draft->fresh()->trashed());
    }

    private function tryMerge(Facility $draft, Facility $register): int
    {
        try {
            Livewire::test(EditFacility::class, ['record' => $draft->getKey()])
                ->callAction('mergeIntoRegisterFacility', ['facility_id' => $register->id]);
        } catch (\Throwable $e) {
            return $e instanceof HttpException ? $e->getStatusCode() : 403;
        }

        return 200;
    }
}
