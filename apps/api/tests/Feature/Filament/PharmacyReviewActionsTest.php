<?php

namespace Tests\Feature\Filament;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Enums\UserKind;
use App\Filament\Resources\ImportReviewItems\Pages\ListImportReviewItems;
use App\Filament\Resources\ImportReviewItems\Pages\ViewImportReviewItem;
use App\Models\Facility;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\PharmacyDutyShift;
use App\Models\SiteSetting;
use App\Models\SourceRecord;
use App\Models\User;
use App\Support\Import\Pharmacies\OnDutyLinker;
use App\Support\Import\Pharmacies\OnDutyPharmacyImporter;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Import review: linking on-duty schedule pharmacies to directory pharmacies
 * („Link suggested pharmacy“, its bulk form, „Link to pharmacy“).
 */
class PharmacyReviewActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();

        $admin = User::factory()->create(['user_kind' => UserKind::Staff, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $admin->syncRoles([RoleCatalog::ADMINISTRATOR]);
        $this->actingAs($admin);
    }

    private function item(string $name, string $reason, ?Facility $suggested = null, array $candidates = []): ImportReviewItem
    {
        $run = ImportRun::start(OnDutyPharmacyImporter::SOURCE, false, null);
        PharmacyDutyShift::query()->create([
            'month' => '2026-10', 'duty_date' => '2026-10-05', 'town' => 'Битола', 'town_key' => 'БИТОЛА',
            'pharmacy_name' => $name, 'name_key' => mb_strtoupper($name), 'mode' => 'hours', 'import_run_id' => $run->getKey(),
        ]);

        return ImportReviewItem::raise(OnDutyPharmacyImporter::SOURCE, ImportReviewKind::Unmatched, OnDutyLinker::itemKey('БИТОЛА', mb_strtoupper($name)), 'On-duty pharmacy '.$name, array_filter([
            'reason' => $reason, 'name' => $name, 'town' => 'Битола', 'town_key' => 'БИТОЛА', 'name_key' => mb_strtoupper($name),
            'suggested_facility_id' => $suggested?->getKey(), 'candidate_facility_ids' => $candidates,
        ]));
    }

    public function test_suggested_links_are_accepted_in_bulk_and_remembered(): void
    {
        $rosa = Facility::factory()->pharmacy()->create(['name' => 'Аптека Роса Вита 2', 'city' => 'Битола']);
        $zegin = Facility::factory()->pharmacy()->create(['name' => 'Аптека Зегин Фарм', 'city' => 'Битола']);
        $first = $this->item('Роса', OnDutyLinker::REASON_SUGGESTED, $rosa, [$rosa->id]);
        $second = $this->item('Зегин', OnDutyLinker::REASON_SUGGESTED, $zegin, [$zegin->id]);
        $other = $this->item('Непозната', OnDutyLinker::REASON_MISSING);

        Livewire::test(ListImportReviewItems::class)
            ->callTableBulkAction('linkSuggestedPharmacies', [$first, $second, $other]);

        $this->assertSame($rosa->id, PharmacyDutyShift::query()->where('pharmacy_name', 'Роса')->value('facility_id'));
        $this->assertSame($zegin->id, PharmacyDutyShift::query()->where('pharmacy_name', 'Зегин')->value('facility_id'));
        $this->assertSame(ImportReviewStatus::Resolved, $first->fresh()->status);
        $this->assertSame(ImportReviewStatus::Open, $other->fresh()->status);
        $this->assertSame(2, SourceRecord::query()->where('source', OnDutyPharmacyImporter::SOURCE)->where('external_key', 'like', OnDutyLinker::ALIAS_PREFIX.'%')->count());
    }

    public function test_staff_pick_the_pharmacy_of_an_ambiguous_one(): void
    {
        $a = Facility::factory()->pharmacy()->create(['name' => 'Аптека Еурофарм 1', 'city' => 'Битола']);
        $b = Facility::factory()->pharmacy()->create(['name' => 'Аптека Еурофарм 2', 'city' => 'Битола']);
        $item = $this->item('Еурофарм', OnDutyLinker::REASON_AMBIGUOUS, null, [$a->id, $b->id]);

        Livewire::test(ViewImportReviewItem::class, ['record' => $item->getRouteKey()])
            ->assertActionVisible('linkPharmacy')
            ->assertActionHidden('linkSuggestedPharmacy')
            ->callAction('linkPharmacy', ['facility_id' => $b->id]);

        $this->assertSame($b->id, PharmacyDutyShift::query()->where('pharmacy_name', 'Еурофарм')->value('facility_id'));
        $this->assertSame(ImportReviewStatus::Resolved, $item->fresh()->status);
        $this->assertSame('linked', $item->fresh()->resolution);
    }
}
