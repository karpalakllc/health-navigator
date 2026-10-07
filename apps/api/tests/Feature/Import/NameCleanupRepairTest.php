<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\SourceRecord;
use App\Models\User;
use App\Support\Import\Names\NameCleanup;
use App\Support\Import\ProvenanceWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * import:repair-name-cleanup: what an earlier import:clean-names wrote with
 * the rules since fixed (B1, S2, S3 of the wave 8 review) is recomputed from
 * the original value in the activity log; staff decisions and locks are
 * never touched; a second run changes nothing. Synthetic names only.
 */
class NameCleanupRepairTest extends TestCase
{
    use RefreshDatabase;

    private int $keys = 0;

    /**
     * A field as the earlier cleanup left it: value, „cleanup“ provenance
     * and the activity-log entry old → new.
     */
    private function cleaned(Doctor|Facility $subject, string $field, ?string $old, ?string $new, string $category, bool $locked = false): void
    {
        $type = $subject instanceof Doctor ? 'doctor' : 'facility';
        $subject->forceFill([$field => $new])->saveQuietly();
        $record = SourceRecord::query()->create([
            'source' => 'fzom', 'external_key' => $type.':'.(++$this->keys), 'subject_type' => $type, 'subject_id' => $subject->id,
            'payload' => [], 'hash' => sha1((string) $this->keys), 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
        FieldProvenance::query()->create([
            'subject_type' => $type, 'subject_id' => $subject->id, 'field' => $field, 'source' => ProvenanceWriter::CLEANUP_SOURCE,
            'source_record_id' => $record->id, 'value' => $new, 'observed_at' => now(), 'locked' => $locked,
        ]);
        NameCleanup::log($subject, $field, $old, $new, $category, null);
    }

    private function doctor(): Doctor
    {
        return Doctor::factory()->unpublished()->create(['title' => null, 'import_source' => 'fzom']);
    }

    public function test_affected_changes_are_recomputed_from_the_original_and_the_rest_is_left_alone(): void
    {
        $quoted = Facility::factory()->unpublished()->create(['city' => 'Тестово']);
        $this->cleaned($quoted, 'name', 'ПЗУ „ДО ДЕНТ“ ТЕСТОВО', 'ПЗУ „до Дент“', 'town+casing');

        $glyph = $this->doctor();
        $this->cleaned($glyph, 'full_name', "Првана Петров\u{0073}ка", 'Првана Петровѕка', 'homoglyph');

        $role = $this->doctor();
        $this->cleaned($role, 'full_name', 'Д-р Ана Петрова - специјалист по педијатрија', 'Ана Петрова-Специјалист По Педијатрија', 'title_in_name+hyphen+casing');
        $this->cleaned($role, 'title', null, 'д-р', 'title_in_name');

        $institution = $this->doctor();
        $this->cleaned($institution, 'full_name', 'ВТОРА ПРИМЕРОВСКА СТОМАТОЛОГ ПЗУ ТЕСТ', 'Втора Примеровска Стоматолог ПЗУ Тест', 'casing');

        $fine = $this->doctor();
        $this->cleaned($fine, 'full_name', 'ТРЕТА ПРИМЕРОВСКА', 'Трета Примеровска', 'casing');

        $staff = $this->doctor();
        $this->cleaned($staff, 'full_name', "Четврта Петров\u{0073}ка", 'Четврта Петровѕка', 'homoglyph');
        $staff->forceFill(['full_name' => 'Четврта Петровска'])->saveQuietly();

        $locked = $this->doctor();
        $this->cleaned($locked, 'full_name', "Петта Петров\u{0073}ка", 'Петта Петровѕка', 'homoglyph', locked: true);

        $batch = $this->doctor();
        $batch->forceFill(['full_name' => 'Xhevdet Rexhepi'])->saveQuietly();
        $other = $this->doctor();
        $other->forceFill(['full_name' => 'Maxim Wolf'])->saveQuietly();
        NameCleanup::raiseBatch('latin_script', [
            ['subject_type' => 'doctor', 'subject_id' => $batch->id, 'field' => 'full_name', 'current' => 'Xhevdet Rexhepi', 'suggestion' => 'Xхевдет Реxхепи'],
            ['subject_type' => 'doctor', 'subject_id' => $other->id, 'field' => 'full_name', 'current' => 'Maxim Wolf', 'suggestion' => 'Маxим Wолф'],
        ], null);

        $logged = Activity::query()->where('log_name', NameCleanup::LOG)->count();

        // A dry run decides and counts, and changes nothing.
        $this->artisan('import:repair-name-cleanup', ['--dry-run' => true])->assertSuccessful();
        $dry = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $dry->counts['facility.name.repaired_b1'] ?? null);
        $this->assertSame(1, $dry->counts['doctor.full_name.repaired_s2'] ?? null);
        $this->assertSame(2, $dry->counts['doctor.full_name.repaired_s3'] ?? null);
        $this->assertSame('ПЗУ „до Дент“', $quoted->fresh()->name);
        $this->assertSame($logged, Activity::query()->where('log_name', NameCleanup::LOG)->count());

        $this->artisan('import:repair-name-cleanup', ['--apply' => true])->assertSuccessful();
        $run = ImportRun::query()->latest('id')->firstOrFail();

        $this->assertSame('ПЗУ „До Дент“', $quoted->fresh()->name);
        // S2: the Latin letter is a person's decision: the original is back, with an item.
        $this->assertSame("Првана Петров\u{0073}ка", $glyph->fresh()->full_name);
        $this->assertSame('mixed_script', ImportReviewItem::query()->where('item_key', 'name:doctor:'.$glyph->id.':full_name')->firstOrFail()->details['problem']);
        // S3: recomputed with today's rules (the dash before the role separates).
        $this->assertSame('Ана Петрова', $role->fresh()->full_name);
        $this->assertSame('д-р', $role->fresh()->title);
        // S3: still uncertain: the original value, the proposal on the item.
        $this->assertSame('ВТОРА ПРИМЕРОВСКА СТОМАТОЛОГ ПЗУ ТЕСТ', $institution->fresh()->full_name);
        $this->assertSame('Втора Примеровска', ImportReviewItem::query()->where('item_key', 'name:doctor:'.$institution->id.':full_name')->firstOrFail()->details['suggestion']);
        // Untouched: right already, changed by staff, locked.
        $this->assertSame('Трета Примеровска', $fine->fresh()->full_name);
        $this->assertSame('Четврта Петровска', $staff->fresh()->full_name);
        $this->assertSame('Петта Петровѕка', $locked->fresh()->full_name);
        $this->assertSame(1, $run->counts['doctor.full_name.skipped_staff'] ?? null);
        $this->assertSame(1, $run->counts['doctor.full_name.skipped_locked'] ?? null);

        // Provenance follows; each repair is logged.
        $this->assertSame('ПЗУ „До Дент“', FieldProvenance::query()->where('subject_type', 'facility')->where('subject_id', $quoted->id)->where('field', 'name')->value('value'));
        $this->assertSame(4, Activity::query()->where('log_name', NameCleanup::LOG)->where('properties->category', 'like', 'repair:%')->count());

        // The Latin batch: today's all-Cyrillic proposal; a name without one gets its own item.
        $item = ImportReviewItem::query()->where('item_key', 'name:batch:latin_script')->firstOrFail();
        $this->assertSame([['subject_type' => 'doctor', 'subject_id' => $batch->id, 'field' => 'full_name', 'current' => 'Xhevdet Rexhepi', 'suggestion' => 'Џевдет Реџепи']], $item->details['changes']);
        $alone = ImportReviewItem::query()->where('item_key', 'name:doctor:'.$other->id.':full_name')->firstOrFail();
        $this->assertNull($alone->details['suggestion']);
        $this->assertSame(ImportReviewKind::Uncertain, $alone->kind);

        // Idempotent: a second run finds nothing to do.
        $entries = Activity::query()->count();
        $items = ImportReviewItem::query()->count();
        $this->artisan('import:repair-name-cleanup', ['--apply' => true])->assertSuccessful();
        $second = ImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame([], array_filter($second->counts ?? [], fn (int $value, string $key): bool => str_contains($key, 'repaired_') || str_starts_with($key, 'batch_') || str_starts_with($key, 'review_items'), ARRAY_FILTER_USE_BOTH));
        $this->assertSame($entries, Activity::query()->count());
        $this->assertSame($items, ImportReviewItem::query()->count());
        $this->assertSame('ПЗУ „До Дент“', $quoted->fresh()->name);
    }

    public function test_a_batch_left_without_proposals_is_closed_and_each_name_gets_its_item(): void
    {
        $doctor = $this->doctor();
        $doctor->forceFill(['full_name' => 'Maxim Wolf'])->saveQuietly();
        $batch = NameCleanup::raiseBatch('latin_script', [
            ['subject_type' => 'doctor', 'subject_id' => $doctor->id, 'field' => 'full_name', 'current' => 'Maxim Wolf', 'suggestion' => 'Маxим Wолф'],
        ], null);

        $this->artisan('import:repair-name-cleanup', ['--apply' => true])->assertSuccessful();

        $this->assertSame(ImportReviewStatus::Dismissed, $batch->fresh()->status);
        $this->assertTrue(ImportReviewItem::query()->open()->where('item_key', 'name:doctor:'.$doctor->id.':full_name')->exists());
    }

    public function test_a_field_a_person_decided_on_is_never_repaired(): void
    {
        $doctor = $this->doctor();
        $this->cleaned($doctor, 'full_name', "Првана Петров\u{0073}ка", 'Првана Петровѕка', 'homoglyph');
        NameCleanup::log($doctor, 'full_name', 'Првана Петровѕка', 'Првана Петровѕка', 'accepted_suggestion:mixed_script', User::factory()->create());

        $this->artisan('import:repair-name-cleanup', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Првана Петровѕка', $doctor->fresh()->full_name);
        $this->assertSame(1, ImportRun::query()->latest('id')->firstOrFail()->counts['skipped_decided_by_staff'] ?? null);
    }
}
