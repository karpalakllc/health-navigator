<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Enums\ImportRunStatus;
use App\Enums\UserKind;
use App\Filament\Resources\ImportReviewItems\Pages\ListImportReviewItems;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\ImportSuppression;
use App\Models\SiteSetting;
use App\Models\SourceRecord;
use App\Models\User;
use App\Support\Import\ImportContext;
use App\Support\Import\Names\NameCleanup;
use App\Support\Import\Names\NameReviewActions;
use App\Support\Import\ProvenanceWriter;
use App\Support\Licences\EloquentLicenceCandidateSource;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * import:clean-names on synthetic profiles: high-confidence fixes applied
 * with provenance and an audit entry, staff edits and locks untouched,
 * uncertain cases and duplicates in the review queue with one-click
 * decisions.
 */
class NameCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['import.disk' => 'local']);
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private int $keys = 0;

    /**
     * A profile as an import left it: the value and its provenance agree.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function doctor(string $name, string $source = 'fzom', array $attributes = []): Doctor
    {
        $doctor = Doctor::factory()->unpublished()->create($attributes + ['full_name' => $name, 'title' => null, 'import_source' => $source]);
        $record = $this->record($source, 'doctor', (int) $doctor->id);
        $this->provenance('doctor', (int) $doctor->id, 'full_name', $source, $name, $record);

        if ($doctor->title !== null) {
            $this->provenance('doctor', (int) $doctor->id, 'title', $source, (string) $doctor->title, $record);
        }

        return $doctor;
    }

    private function facility(string $name, string $city, string $source = 'fzom'): Facility
    {
        $facility = Facility::factory()->unpublished()->create(['name' => $name, 'city' => $city, 'import_source' => $source]);
        $this->provenance('facility', (int) $facility->id, 'name', $source, $name, $this->record($source, 'facility', (int) $facility->id));

        return $facility;
    }

    private function record(string $source, string $type, int $id): int
    {
        return (int) SourceRecord::query()->create([
            'source' => $source,
            'external_key' => ($type === 'doctor' && $source === 'website' ? 'worker:0:' : $type.':').(++$this->keys),
            'subject_type' => $type,
            'subject_id' => $id,
            'payload' => [],
            'hash' => sha1((string) $this->keys),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ])->getKey();
    }

    private function provenance(string $type, int $id, string $field, string $source, string $value, int $recordId, bool $locked = false): void
    {
        FieldProvenance::query()->create([
            'subject_type' => $type, 'subject_id' => $id, 'field' => $field, 'source' => $source,
            'source_record_id' => $recordId, 'value' => $value, 'observed_at' => now(), 'locked' => $locked,
        ]);
    }

    private function staff(): User
    {
        $user = User::factory()->create(['user_kind' => UserKind::Staff, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $user->syncRoles([RoleCatalog::ADMINISTRATOR]);

        return $user;
    }

    public function test_a_dry_run_changes_nothing_and_reports_privately(): void
    {
        $doctor = $this->doctor('Првана Примеровска - Тестова');
        $facility = $this->facility('ПЗУ Орд.По Општа Медицина Тест Медика Тестово', 'Тестово');

        $this->artisan('import:clean-names', ['--dry-run' => true])->assertSuccessful();

        $run = ImportRun::query()->where('source', 'cleanup')->latest('id')->firstOrFail();
        $this->assertTrue($run->dry_run);
        $this->assertSame(ImportRunStatus::Succeeded, $run->status);
        $this->assertSame(1, $run->counts['doctor.full_name.changed'] ?? null);
        $this->assertSame(1, $run->counts['facility.name.changed'] ?? null);

        $this->assertSame('Првана Примеровска - Тестова', $doctor->fresh()->full_name);
        $this->assertSame('ПЗУ Орд.По Општа Медицина Тест Медика Тестово', $facility->fresh()->name);
        $this->assertSame(0, Activity::query()->where('log_name', NameCleanup::LOG)->count());
        $this->assertSame(0, ImportReviewItem::query()->count());

        // The report holds names: the private import disk only.
        $csv = Storage::disk('local')->get((string) $run->diff_path);
        $this->assertStringStartsWith('imports/runs/', (string) $run->diff_path);
        $this->assertStringContainsString('Првана Примеровска-Тестова', (string) $csv);
        $this->assertStringContainsString('would_apply', (string) $csv);
    }

    public function test_apply_fixes_imported_names_with_provenance_and_an_audit_entry(): void
    {
        $doctor = $this->doctor('Првана Примеровска - Тестова', 'fzom');
        $glyph = $this->doctor("Втора Пример\u{0061}вска");
        $titled = $this->doctor('Трета Примеровска', 'website', ['title' => 'Проф д-р др. сци']);
        $facility = $this->facility('ПЗУ Орд.По Општа Медицина Тест Медика Тестово', 'Тестово');
        $public = $this->facility('ЈЗУ Здравствен Дом Тестово', 'Тестово');

        $this->artisan('import:clean-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Првана Примеровска-Тестова', $doctor->fresh()->full_name);
        $this->assertSame('Втора Примеравска', $glyph->fresh()->full_name);
        $this->assertSame('проф. д-р д-р сци.', $titled->fresh()->title);
        $this->assertSame('ПЗУ Ординација по општа медицина Тест Медика', $facility->fresh()->name);
        $this->assertSame('ЈЗУ Здравствен дом Тестово', $public->fresh()->name);

        $provenance = FieldProvenance::query()->where('subject_type', 'doctor')->where('subject_id', $doctor->id)->where('field', 'full_name')->firstOrFail();
        $this->assertSame('cleanup', $provenance->source);
        $this->assertSame('Првана Примеровска-Тестова', $provenance->value);
        $this->assertNotNull($provenance->source_record_id, 'The record behind the value is kept.');

        $entry = Activity::query()->where('log_name', NameCleanup::LOG)->where('subject_type', $doctor->getMorphClass())->where('subject_id', $doctor->id)->firstOrFail();
        $this->assertSame('full_name', $entry->properties['field']);
        $this->assertSame('Првана Примеровска - Тестова', $entry->properties['old']);
        $this->assertSame('Првана Примеровска-Тестова', $entry->properties['new']);

        // A second run finds nothing left to do.
        $this->artisan('import:clean-names', ['--apply' => true])->assertSuccessful();
        $second = ImportRun::query()->where('source', 'cleanup')->latest('id')->firstOrFail();
        $this->assertArrayNotHasKey('doctor.full_name.changed', $second->counts ?? []);
        $this->assertArrayNotHasKey('facility.name.changed', $second->counts ?? []);
        $this->assertArrayNotHasKey('doctor.title.changed', $second->counts ?? []);
    }

    public function test_staff_edits_and_locked_fields_are_never_touched(): void
    {
        $edited = $this->doctor('Првана Примеровска - Тестова');
        $edited->forceFill(['full_name' => 'Првана Примеровска - Уредена'])->save();
        $locked = Doctor::factory()->unpublished()->create(['full_name' => 'Втора Примеровска - Тестова', 'title' => null, 'import_source' => 'fzom']);
        $this->provenance('doctor', (int) $locked->id, 'full_name', 'fzom', 'Втора Примеровска - Тестова', $this->record('fzom', 'doctor', (int) $locked->id), locked: true);
        $handMade = Doctor::factory()->unpublished()->create(['full_name' => 'Трета Примеровска - Тестова', 'title' => null]);

        $this->artisan('import:clean-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Првана Примеровска - Уредена', $edited->fresh()->full_name);
        $this->assertSame('Втора Примеровска - Тестова', $locked->fresh()->full_name);
        $this->assertSame('Трета Примеровска - Тестова', $handMade->fresh()->full_name);

        $run = ImportRun::query()->where('source', 'cleanup')->latest('id')->firstOrFail();
        $this->assertSame(2, $run->counts['doctor.full_name.skipped_staff'] ?? null);
        $this->assertSame(1, $run->counts['doctor.full_name.skipped_locked'] ?? null);
    }

    public function test_uncertain_names_are_queued_and_one_click_accepts_or_keeps(): void
    {
        $latin = $this->doctor('Prvana Primerovska', 'website');
        $otherLatin = $this->doctor('Vtora Primerovska', 'website');
        $title = $this->doctor('Трета Примеровска', 'website', ['title' => 'капетан д-р']);

        $this->artisan('import:clean-names', ['--apply' => true])->assertSuccessful();

        // All Latin-script names in one item, one proposal each.
        $batch = ImportReviewItem::query()->where('source', 'cleanup')->where('item_key', 'name:batch:latin_script')->firstOrFail();
        $this->assertSame(ImportReviewKind::Uncertain, $batch->kind);
        $this->assertSame(NameCleanup::REASON, $batch->details['reason']);
        $this->assertCount(2, $batch->details['changes']);
        $this->assertStringContainsString('Prvana Primerovska → Првана Примеровска', (string) $batch->details['proposals']);

        $titleItem = ImportReviewItem::query()->where('source', 'cleanup')->where('item_key', 'name:doctor:'.$title->id.':title')->firstOrFail();
        $this->assertSame('title_unrecognised', $titleItem->details['problem']);
        $this->assertFalse(NameReviewActions::hasSuggestion($titleItem));

        // A profile edited since is skipped; the others take the proposal.
        $otherLatin->forceFill(['full_name' => 'Vtora Primerovska-Urednik'])->save();
        $this->actingAs($staff = $this->staff());
        Livewire::test(ListImportReviewItems::class)
            ->assertTableActionVisible('acceptSuggestion', $batch)
            ->assertTableActionHidden('dismiss', $batch)
            ->callTableAction('acceptSuggestion', $batch)
            ->assertTableActionHidden('acceptSuggestion', $titleItem)
            ->callTableAction('keepName', $titleItem);

        $this->assertSame('Првана Примеровска', $latin->fresh()->full_name);
        $this->assertSame('Vtora Primerovska-Urednik', $otherLatin->fresh()->full_name);
        $this->assertSame(ImportReviewStatus::Resolved, $batch->fresh()->status);
        $this->assertSame(ImportReviewStatus::Dismissed, $titleItem->fresh()->status);
        $this->assertSame('капетан д-р', $title->fresh()->title);
        $this->assertSame((int) $staff->id, (int) Activity::query()->where('log_name', NameCleanup::LOG)->where('subject_id', $latin->id)->value('causer_id'));

        // Accepted is a staff decision: the next cleanup leaves it, and the
        // kept title stays dismissed.
        $this->artisan('import:clean-names', ['--apply' => true])->assertSuccessful();
        $this->assertSame('Првана Примеровска', $latin->fresh()->full_name);
        $this->assertSame(ImportReviewStatus::Dismissed, $titleItem->fresh()->status);
        $this->assertSame(0, ImportReviewItem::query()->open()->where('source', 'cleanup')->where('item_key', 'name:doctor:'.$title->id.':title')->count());
    }

    /**
     * An uncertain name is never partly rewritten: the profile keeps its
     * value (and title), the proposal waits on the review item.
     */
    public function test_an_uncertain_name_keeps_its_value(): void
    {
        $doctor = $this->doctor('Д-р ПРВАНА ПРИМЕРОВСКА СТОМАТОЛОГ ПЗУ ТЕСТ');
        $mixed = $this->doctor("Втора Примеров\u{0073}ка");

        $this->artisan('import:clean-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Д-р ПРВАНА ПРИМЕРОВСКА СТОМАТОЛОГ ПЗУ ТЕСТ', $doctor->fresh()->full_name);
        $this->assertNull($doctor->fresh()->title);
        $this->assertSame("Втора Примеров\u{0073}ка", $mixed->fresh()->full_name);
        $this->assertSame(0, Activity::query()->where('log_name', NameCleanup::LOG)->count());

        $item = ImportReviewItem::query()->where('item_key', 'name:doctor:'.$doctor->id.':full_name')->firstOrFail();
        $this->assertSame('institution_in_name', $item->details['problem']);
        $this->assertSame('Првана Примеровска', $item->details['suggestion']);
        $this->assertSame('mixed_script', ImportReviewItem::query()->where('item_key', 'name:doctor:'.$mixed->id.':full_name')->firstOrFail()->details['problem']);
    }

    /**
     * After the cleanup, the source's next run writes the same value: no
     * conflict, and a real change from the source is still taken.
     */
    public function test_the_next_import_takes_the_cleaned_value_as_its_own(): void
    {
        $doctor = $this->doctor('Првана Примеровска - Тестова', 'fzom');
        $this->artisan('import:clean-names', ['--apply' => true])->assertSuccessful();

        $context = new ImportContext(ImportRun::start('fzom', false), 'fzom', false);
        $writer = new ProvenanceWriter($context);
        $doctor = $doctor->fresh();

        $this->assertSame('unchanged', $writer->scalar($doctor, 'full_name', 'Првана Примеровска-Тестова', false));
        $this->assertSame('fzom', $writer->sourceOf($doctor, 'full_name'));

        $fresh = Doctor::query()->findOrFail($doctor->id);
        $other = $this->doctor('Втора Примеровска - Тестова', 'fzom');
        $this->artisan('import:clean-names', ['--apply' => true])->assertSuccessful();
        $writer = new ProvenanceWriter(new ImportContext(ImportRun::start('fzom', false), 'fzom', false));
        $this->assertSame('written', $writer->scalar($other->fresh(), 'full_name', 'Втора Примеровска-Нова', false));
        $this->assertSame(0, ImportReviewItem::query()->where('kind', ImportReviewKind::Conflict)->count());
        $this->assertSame('Првана Примеровска-Тестова', $fresh->full_name);
    }

    /**
     * Комора matching finds the same profiles before and after the cleanup.
     */
    public function test_licence_matching_is_the_same_after_the_cleanup(): void
    {
        $doctor = $this->doctor('ПРВАНА ПРИМЕРОВСКА - ТЕСТОВА', 'fzom');
        $glyph = $this->doctor("Втора Пример\u{0061}вска", 'fzom');
        $source = new EloquentLicenceCandidateSource;
        $before = [
            array_map(fn ($c) => $c->doctorId, $source->candidatesFor('Примеровска-Тестова Првана')),
            array_map(fn ($c) => $c->doctorId, $source->candidatesFor('ВТОРА ПРИМЕРАВСКА')),
        ];

        $this->artisan('import:clean-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Првана Примеровска-Тестова', $doctor->fresh()->full_name);
        $this->assertSame([[(int) $doctor->id], [(int) $glyph->id]], $before);
        $this->assertSame($before, [
            array_map(fn ($c) => $c->doctorId, $source->candidatesFor('Примеровска-Тестова Првана')),
            array_map(fn ($c) => $c->doctorId, $source->candidatesFor('ВТОРА ПРИМЕРАВСКА')),
        ]);
    }

    public function test_duplicates_are_grouped_and_merged_only_on_request(): void
    {
        $register = $this->facility('ПЗУ Тест Медика', 'Тестово');
        $site = $this->facility('Болница Пример', 'Тестово', 'website');
        $fzomDoctor = $this->doctor('Првана Примеровска', 'fzom', ['city' => 'Тестово']);
        DB::table('doctor_facility')->insert(['doctor_id' => $fzomDoctor->id, 'facility_id' => $register->id, 'is_primary' => true, 'source' => 'fzom', 'created_at' => now(), 'updated_at' => now()]);
        $draft = $this->doctor('ПРИМЕРОВСКА ПРВАНА', 'website', ['city' => 'Тестово', 'title' => 'проф. д-р']);
        DB::table('doctor_facility')->insert(['doctor_id' => $draft->id, 'facility_id' => $site->id, 'is_primary' => true, 'source' => 'website', 'work_unit' => 'Оддел за тест', 'created_at' => now(), 'updated_at' => now()]);
        // A public website profile is never merged away.
        $public = $this->doctor('Втора Примеровска', 'website', ['city' => 'Тестово', 'is_published' => true, 'published_at' => now()]);
        $this->doctor('Втора Примеровска', 'fzom', ['city' => 'Тестово']);

        $this->artisan('import:clean-names', ['--apply' => true])->assertSuccessful();

        // Nothing merged by itself.
        $this->assertNotNull(Doctor::query()->find($draft->id));
        $group = ImportReviewItem::query()->where('source', 'cleanup')->where('details->reason', 'possible_duplicate')->where('details->kind', 'website_into_register')->firstOrFail();
        $this->assertSame([[(int) $draft->id, (int) $fzomDoctor->id]], $group->details['pairs']);
        $this->assertSame((int) $site->id, (int) $group->subject_id);
        $this->assertSame(0, ImportReviewItem::query()->where('details->pairs', 'like', '%'.$public->id.'%')->count());

        $this->actingAs($this->staff());
        Livewire::test(ListImportReviewItems::class)->callTableAction('mergeDuplicates', $group);

        $this->assertNull(Doctor::withTrashed()->find($draft->id), 'The draft is gone…');
        $this->assertSame(0, ImportSuppression::query()->count(), '…without a suppression: the person stays.');
        $merged = $fzomDoctor->fresh();
        $this->assertEqualsCanonicalizing([(int) $register->id, (int) $site->id], $merged->facilities()->pluck('facilities.id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame('Оддел за тест', DB::table('doctor_facility')->where('doctor_id', $merged->id)->where('facility_id', $site->id)->value('work_unit'));
        $this->assertSame('проф. д-р', $merged->title);
        $this->assertSame(1, SourceRecord::query()->where('source', 'website')->where('subject_id', $merged->id)->count(), 'The staff page now feeds the profile.');
        $this->assertSame(ImportReviewStatus::Resolved, $group->fresh()->status);
        $this->assertTrue(Activity::query()->where('log_name', NameCleanup::LOG)->where('event', 'merged')->where('subject_id', $merged->id)->exists());
        $this->assertNotNull(Doctor::query()->find($public->id));
    }
}
