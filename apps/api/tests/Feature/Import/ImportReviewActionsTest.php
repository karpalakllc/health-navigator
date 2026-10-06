<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\Doctor;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\User;
use App\Support\Import\ImportReviewActions;
use App\Support\Import\ProvenanceWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportReviewActionsTest extends TestCase
{
    use RefreshDatabase;

    private function conflict(Doctor $doctor, string $current, string $incoming): ImportReviewItem
    {
        return ImportReviewItem::raise('fzom', ImportReviewKind::Conflict, 'doctor:'.$doctor->getKey().':city', 'Конфликт', [
            'field' => 'city', 'current' => $current, 'incoming' => $incoming,
        ], $doctor);
    }

    public function test_use_imported_value_refuses_a_field_locked_since_the_conflict_was_raised(): void
    {
        $doctor = Doctor::factory()->create(['city' => 'Рачно']);
        $item = $this->conflict($doctor, 'Рачно', 'Увезено');
        $staff = User::factory()->create();
        ProvenanceWriter::setLock($doctor, 'city', true, $staff);

        $this->assertFalse(app(ImportReviewActions::class)->acceptIncoming($item, $staff));

        $this->assertSame('Рачно', $doctor->refresh()->city);
        $this->assertTrue((bool) FieldProvenance::query()->where('subject_id', $doctor->getKey())->where('field', 'city')->value('locked'));
        $this->assertSame(ImportReviewStatus::Open, $item->refresh()->status);
    }

    public function test_use_imported_value_refuses_when_the_profile_changed_since(): void
    {
        $doctor = Doctor::factory()->create(['city' => 'Рачно']);
        $item = $this->conflict($doctor, 'Рачно', 'Увезено');
        $doctor->update(['city' => 'Сосема ново']);

        $this->assertFalse(app(ImportReviewActions::class)->acceptIncoming($item, User::factory()->create()));
        $this->assertSame('Сосема ново', $doctor->refresh()->city);
    }

    public function test_an_item_already_closed_elsewhere_is_not_acted_on_again(): void
    {
        $doctor = Doctor::factory()->create(['is_published' => false]);
        $fromRegister = ImportReviewItem::raise('fzom', ImportReviewKind::New, 'doctor:'.$doctor->getKey(), 'Нов', [], $doctor);
        $fromWebsite = ImportReviewItem::raise('website', ImportReviewKind::New, 'doctor:'.$doctor->getKey(), 'Нов', [], $doctor);
        $staff = User::factory()->create();
        $actions = app(ImportReviewActions::class);

        // A bulk publish of both: the second (stale) item counts nothing.
        $this->assertTrue($actions->publish($fromRegister, $staff));
        $this->assertFalse($actions->publish($fromWebsite, $staff));
        $this->assertSame(ImportReviewStatus::Resolved, $fromWebsite->refresh()->status);
    }

    public function test_a_long_item_key_fits_the_column_on_every_database_and_stays_idempotent(): void
    {
        // A website wording of 200 characters: 'specialty:website:<wording>' is far over 128.
        $key = 'specialty:website:'.str_repeat('СУБСПЕЦИЈАЛИЗАЦИЈА ПО ', 9);

        $first = ImportReviewItem::raise('website', ImportReviewKind::Unmatched, $key, 'Долг текст');
        $second = ImportReviewItem::raise('website', ImportReviewKind::Unmatched, $key, 'Долг текст');

        $this->assertLessThanOrEqual(ImportReviewItem::KEY_LENGTH, mb_strlen($first->item_key));
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertStringStartsWith('specialty:website:СУБСПЕЦИЈАЛИЗАЦИЈА', $first->item_key);

        ImportReviewItem::autoResolve('website', ImportReviewKind::Unmatched, $key, 'mapped');
        $this->assertSame(ImportReviewStatus::Resolved, $first->refresh()->status);
    }
}
