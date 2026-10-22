<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\PharmacyDutyShift;
use App\Models\SourceRecord;
use App\Models\User;
use App\Support\Import\ImportContext;
use App\Support\Import\Pharmacies\OnDutyLinker;
use App\Support\Import\Pharmacies\OnDutyNames;
use App\Support\Import\Pharmacies\OnDutyPharmacyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Staff unlink an on-duty schedule pharmacy from a directory pharmacy
 * (OnDutyLinker::unlinkByStaff) and link by staff fills an empty phone.
 */
class PharmacyUnlinkTest extends TestCase
{
    use RefreshDatabase;

    private function shift(string $name, array $overrides = []): PharmacyDutyShift
    {
        $run = ImportRun::start(OnDutyPharmacyImporter::SOURCE, false, null);

        return PharmacyDutyShift::query()->create([
            'month' => '2026-10', 'duty_date' => '2026-10-07', 'town' => 'Велес', 'town_key' => OnDutyNames::townKey('Велес'),
            'pharmacy_name' => $name, 'name_key' => OnDutyNames::pharmacyKey($name, 'Велес'), 'phone' => '043/111-222',
            'mode' => PharmacyDutyShift::MODE_HOURS, 'import_run_id' => $run->getKey(), ...$overrides,
        ]);
    }

    private function relink(): void
    {
        $context = new ImportContext(ImportRun::start(OnDutyPharmacyImporter::SOURCE, false, null), OnDutyPharmacyImporter::SOURCE, false);
        app(OnDutyLinker::class)->relinkAll($context);
    }

    public function test_unlinking_forgets_the_alias_relinks_by_rules_to_another_pharmacy_and_never_returns(): void
    {
        $plain = Facility::factory()->pharmacy()->create(['name' => 'Аптека Магна Фарм', 'city' => 'Велес', 'address' => 'Д. Влахов 5']);
        $second = Facility::factory()->pharmacy()->create(['name' => 'Аптека Магна Фарм - 2', 'city' => 'Велес', 'address' => 'Друга 1']);
        $shift = $this->shift('Магна Фарм', ['facility_id' => $second->id, 'address' => 'Д. Влахов 5']);
        $staff = User::factory()->create();
        $item = ImportReviewItem::raise(OnDutyPharmacyImporter::SOURCE, ImportReviewKind::Unmatched, OnDutyLinker::itemKey($shift->town_key, $shift->name_key), 'x', ['town_key' => $shift->town_key, 'name_key' => $shift->name_key]);
        $linker = app(OnDutyLinker::class);
        $this->assertTrue($linker->linkByStaff($item, $second->id, $staff));
        $this->assertArrayHasKey($shift->town_key.'|'.$shift->name_key, $linker->linkedSchedules($second));

        $result = app(OnDutyLinker::class)->unlinkByStaff($second, $shift->town_key, $shift->name_key, $staff);

        $this->assertSame(1, $result['shifts']);
        $this->assertSame($plain->id, $result['relinked_to']);
        $this->assertSame($plain->id, $shift->fresh()->facility_id);
        $this->assertSame(0, SourceRecord::query()->where('external_key', OnDutyLinker::aliasKey($shift->town_key, $shift->name_key))->count());
        $this->assertSame(1, SourceRecord::query()->where('external_key', 'like', OnDutyLinker::REJECT_PREFIX.'%')->count());
        $activity = Activity::query()->where('event', 'on_duty_unlinked')->first();
        $this->assertSame($second->id, $activity->subject_id);
        $this->assertSame($staff->id, $activity->causer_id);

        // A later import run does not link the rejected pair again.
        $shift->forceFill(['facility_id' => null])->save();
        $this->relink();
        $this->assertSame($plain->id, $shift->fresh()->facility_id);
    }

    public function test_the_rejected_pharmacy_is_not_linked_by_the_automatic_rules_and_the_schedule_is_queued(): void
    {
        $only = Facility::factory()->pharmacy()->create(['name' => 'Аптека Роса', 'city' => 'Велес']);
        $shift = $this->shift('Роса', ['facility_id' => $only->id]);

        $result = app(OnDutyLinker::class)->unlinkByStaff($only, $shift->town_key, $shift->name_key, User::factory()->create());

        $this->assertNull($result['relinked_to']);
        $this->assertNull($shift->fresh()->facility_id);
        $this->assertTrue(ImportReviewItem::query()->open()->where('item_key', OnDutyLinker::itemKey($shift->town_key, $shift->name_key))->exists());

        $this->relink();
        $this->assertNull($shift->fresh()->facility_id);

        // Staff may link it again later: the rejection is lifted.
        $item = ImportReviewItem::query()->open()->firstOrFail();
        $this->assertTrue(app(OnDutyLinker::class)->linkByStaff($item, $only->id, User::factory()->create()));
        $this->assertSame($only->id, $shift->fresh()->facility_id);
        $this->assertSame(0, SourceRecord::query()->where('external_key', 'like', OnDutyLinker::REJECT_PREFIX.'%')->count());
    }

    public function test_staff_can_name_the_right_pharmacy_while_unlinking(): void
    {
        $wrong = Facility::factory()->pharmacy()->create(['name' => 'Аптека Роса', 'city' => 'Велес']);
        $right = Facility::factory()->pharmacy()->create(['name' => 'Аптека Друга', 'city' => 'Велес']);
        $shift = $this->shift('Роса', ['facility_id' => $wrong->id]);

        $result = app(OnDutyLinker::class)->unlinkByStaff($wrong, $shift->town_key, $shift->name_key, User::factory()->create(), $right->id);

        $this->assertSame($right->id, $result['relinked_to']);
        $this->assertSame($right->id, $shift->fresh()->facility_id);
        $this->relink();
        $this->assertSame($right->id, $shift->fresh()->facility_id);
    }

    public function test_linking_by_staff_fills_an_empty_phone_but_never_replaces_one(): void
    {
        $empty = Facility::factory()->pharmacy()->create(['name' => 'Аптека Роса', 'city' => 'Велес', 'phone' => null]);
        $kept = Facility::factory()->pharmacy()->create(['name' => 'Аптека Зора', 'city' => 'Велес', 'phone' => '070 000 000']);
        $first = $this->shift('Роса');
        $second = $this->shift('Зора');
        $staff = User::factory()->create();

        foreach ([[$first, $empty], [$second, $kept]] as [$shift, $pharmacy]) {
            $item = ImportReviewItem::raise(OnDutyPharmacyImporter::SOURCE, ImportReviewKind::Unmatched, OnDutyLinker::itemKey($shift->town_key, $shift->name_key), 'x', ['town_key' => $shift->town_key, 'name_key' => $shift->name_key]);
            $this->assertTrue(app(OnDutyLinker::class)->linkByStaff($item, $pharmacy->id, $staff));
            $this->assertSame(ImportReviewStatus::Resolved, $item->fresh()->status);
        }

        $this->assertSame('043/111-222', $empty->fresh()->phone);
        $this->assertSame(OnDutyPharmacyImporter::SOURCE, FieldProvenance::query()->where('subject_id', $empty->id)->where('field', 'phone')->value('source'));
        $this->assertSame('070 000 000', $kept->fresh()->phone);
    }
}
