<?php

namespace Tests\Feature\Import;

use App\Enums\FacilityType;
use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\Activity;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\FacilityMedia;
use App\Models\FieldProvenance;
use App\Models\ImportReviewItem;
use App\Models\Review;
use App\Models\SourceRecord;
use App\Models\User;
use App\Support\Import\Facilities\FacilityDuplicateMatcher;
use App\Support\Import\Facilities\FacilityDuplicatePlanner;
use App\Support\Import\Facilities\FacilityMerger;
use App\Support\Import\Facilities\FacilityMergeResult;
use App\Support\Import\ProvenanceWriter;
use App\Support\Verification\Engine\EvidenceLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeMeilisearchEngine;
use Tests\TestCase;

/**
 * Website-only facilities that duplicate a register facility: the matcher's
 * proposals, the merge (FacilityMerger), the plan run by hand
 * (import:facility-duplicates) and what the next website import does.
 */
class FacilityMergeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        config(['import.disk' => 'local', 'media.disk' => 'public']);
        FacilityMerger::forgetSchema();
        $this->admin = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function register(array $attributes = []): Facility
    {
        $facility = Facility::factory()->create($attributes + [
            'name' => 'ЈЗУ Универ. Клин. за Инфек. Болес. и Фебрил. Состојби',
            'city' => 'Скопје - Центар',
            'address' => 'Ул.Водњанска БР.17',
            'type' => FacilityType::Hospital,
            'phone' => null,
            'email' => null,
            'website' => null,
            'description' => null,
        ]);
        $facility->forceFill(['import_source' => 'fzom'])->save();
        SourceRecord::query()->create([
            'source' => 'fzom', 'external_key' => 'facility:zu:'.$facility->id, 'subject_type' => 'facility', 'subject_id' => $facility->id,
            'payload' => ['name' => $facility->name, 'town' => 'Скопје'], 'hash' => 'h', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        return $facility;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function draft(array $attributes = [], string $slug = 'uk-infektivni'): Facility
    {
        $facility = Facility::factory()->unpublished()->create($attributes + [
            'name' => 'ЈЗУ Универзитетска клиника за инфективни болести и фебрилни состојби – Скопје',
            'city' => 'Скопје',
            'address' => 'ул. Мајка Тереза бр. 17, 1000 Скопје',
            'type' => FacilityType::Hospital,
            'phone' => '+389 2 3228 224',
            'email' => null,
            'website' => 'https://infektivnaklinika.mk',
            'description' => null,
        ]);
        $facility->forceFill(['import_source' => 'website'])->save();
        $record = SourceRecord::query()->create([
            'source' => 'website', 'external_key' => 'institution:public-hospitals:'.$slug, 'subject_type' => 'facility', 'subject_id' => $facility->id,
            'payload' => ['name_mk' => $facility->name, 'phones' => [$facility->phone]], 'hash' => 'h', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        foreach (['phone', 'website'] as $field) {
            FieldProvenance::query()->create([
                'subject_type' => 'facility', 'subject_id' => $facility->id, 'field' => $field, 'source' => 'website',
                'source_record_id' => $record->id, 'source_url' => 'https://infektivnaklinika.mk/kontakt', 'value' => $facility->getAttribute($field), 'observed_at' => now(),
            ]);
        }

        return $facility;
    }

    /**
     * @param  array<string, mixed>  $pivot
     */
    private function link(Doctor $doctor, Facility $facility, array $pivot = []): void
    {
        DB::table('doctor_facility')->insert($pivot + [
            'doctor_id' => $doctor->id, 'facility_id' => $facility->id, 'is_primary' => true, 'source' => 'website',
            'work_unit' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function staffEntry(Facility $facility, Doctor $doctor, string $name): SourceRecord
    {
        return SourceRecord::query()->create([
            'source' => 'website', 'external_key' => 'worker:'.$facility->id.':'.$name, 'subject_type' => 'doctor', 'subject_id' => $doctor->id,
            'payload' => ['full_name' => $name, 'slice' => 'public-hospitals'], 'hash' => 'h', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
    }

    public function test_the_merge_moves_workplaces_images_records_and_empty_fields_and_retires_the_draft(): void
    {
        $engine = FakeMeilisearchEngine::install();
        $register = $this->register(['email' => 'info@register.invalid']);
        $draft = $this->draft(['email' => 'info@draft.invalid', 'is_published' => true, 'published_at' => now(), 'avatar_url' => 'media/facilities/logo.png']);
        $this->assertArrayHasKey($draft->id, $engine->indexes['facilities'] ?? []);

        $moving = Doctor::factory()->create(['full_name' => 'Прва Тестовска']);
        $both = Doctor::factory()->create(['full_name' => 'Втора Тестовска']);
        $this->link($moving, $draft, ['work_unit' => 'Оддел А']);
        $this->link($both, $register, ['is_primary' => false, 'source' => 'fzom']);
        $this->link($both, $draft, ['is_primary' => true, 'work_unit' => 'Оддел Б']);
        $entry = $this->staffEntry($draft, $moving, 'ПРВА ТЕСТОВСКА');
        FacilityMedia::query()->create(['facility_id' => $draft->id, 'kind' => FacilityMedia::KIND_LOGO, 'source' => 'website', 'source_url' => 'https://infektivnaklinika.mk/logo.png', 'content_hash' => 'abc', 'path' => 'media/facilities/logo.png', 'status' => FacilityMedia::STATUS_TRADEMARK, 'position' => 0]);
        $department = Department::factory()->create(['name' => 'Ургентен центар']);
        DB::table('department_facility')->insert(['department_id' => $department->id, 'facility_id' => $draft->id, 'created_at' => now(), 'updated_at' => now()]);
        $item = ImportReviewItem::raise('website', ImportReviewKind::New, 'facility:'.$draft->id, $draft->name, [], $draft);
        ImportReviewItem::raise(EvidenceLoader::ENGINE_SOURCE, ImportReviewKind::Uncertain, EvidenceLoader::FLAGGED_SITE_KEY.$draft->id, 'Flagged', [])
            ->resolve(ImportReviewStatus::Resolved, EvidenceLoader::TRUSTED_RESOLUTION, $this->admin);
        // Staff locked the register's phone (empty on purpose).
        ProvenanceWriter::setLock($register, 'phone', true, $this->admin);

        $result = app(FacilityMerger::class)->merge($draft->id, $register->id, $this->admin);

        $this->assertSame(FacilityMergeResult::MERGED, $result->status, (string) $result->reason);
        // Workplaces: moved with their department; a doctor linked to both keeps one link, primary now.
        $this->assertSame([$register->id], DB::table('doctor_facility')->where('doctor_id', $moving->id)->pluck('facility_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame('Оддел А', DB::table('doctor_facility')->where('doctor_id', $moving->id)->value('work_unit'));
        $kept = DB::table('doctor_facility')->where('doctor_id', $both->id)->get();
        $this->assertCount(1, $kept);
        $this->assertTrue((bool) $kept[0]->is_primary);
        $this->assertSame('Оддел Б', $kept[0]->work_unit);
        $this->assertSame(0, DB::table('doctor_facility')->where('facility_id', $draft->id)->count());

        // Empty fields filled with provenance; a filled or locked field is kept.
        $register->refresh();
        $this->assertSame('https://infektivnaklinika.mk', $register->website);
        $this->assertSame('media/facilities/logo.png', $register->avatar_url);
        $this->assertSame('info@register.invalid', $register->email);
        $this->assertNull($register->phone);
        $this->assertSame(['website', 'avatar_url'], $result->fieldsCopied);
        $provenance = FieldProvenance::query()->where('subject_type', 'facility')->where('subject_id', $register->id)->where('field', 'website')->firstOrFail();
        $this->assertSame('website', $provenance->source);
        $this->assertSame('https://infektivnaklinika.mk/kontakt', $provenance->source_url);

        // Images, departments, source records, the trust decision follow.
        $this->assertSame($register->id, (int) FacilityMedia::query()->value('facility_id'));
        $this->assertSame([$department->id], DB::table('department_facility')->where('facility_id', $register->id)->pluck('department_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame($register->id, (int) SourceRecord::query()->where('external_key', 'institution:public-hospitals:uk-infektivni')->value('subject_id'));
        $this->assertSame('worker:'.$register->id.':ПРВА ТЕСТОВСКА', $entry->fresh()->external_key);
        $this->assertTrue(ImportReviewItem::query()->where('item_key', EvidenceLoader::FLAGGED_SITE_KEY.$register->id)->exists());

        // The draft: soft-deleted, pointing at the register facility, no longer holding the files.
        $gone = Facility::withTrashed()->findOrFail($draft->id);
        $this->assertTrue($gone->trashed());
        $this->assertSame($register->id, $gone->merged_into_id);
        $this->assertNull($gone->avatar_url);
        $this->assertSame('merged', $item->fresh()->resolution);
        $this->assertArrayNotHasKey($draft->id, $engine->indexes['facilities']);
        $this->assertSame(['Ургентен центар'], $engine->indexes['facilities'][$register->id]['department_names']);
        $this->assertSame(2, Activity::query()->where('log_name', FacilityMerger::LOG)->count());
        $this->assertSame($this->admin->id, (int) Activity::query()->where('log_name', FacilityMerger::LOG)->where('subject_id', $register->id)->value('causer_id'));

        // Again: nothing to do, nothing changes.
        $before = DB::table('doctor_facility')->count();
        $this->assertSame(FacilityMergeResult::ALREADY_MERGED, app(FacilityMerger::class)->merge($draft->id, $register->id, $this->admin)->status);
        $this->assertSame($before, DB::table('doctor_facility')->count());
        $this->assertSame(2, Activity::query()->where('log_name', FacilityMerger::LOG)->count());
    }

    public function test_a_staff_page_entry_the_register_site_already_has_is_folded(): void
    {
        $register = $this->register();
        $draft = $this->draft();
        $doctor = Doctor::factory()->create();
        $this->link($doctor, $draft);
        $old = $this->staffEntry($draft, $doctor, 'ИМЕ ПРЕЗИМЕ');
        $twin = $this->staffEntry($register, $doctor, 'ИМЕ ПРЕЗИМЕ');
        $old->forceFill(['last_seen_at' => now()->addDay()])->save();
        FieldProvenance::query()->create(['subject_type' => 'doctor', 'subject_id' => $doctor->id, 'field' => 'title', 'source' => 'website', 'source_record_id' => $old->id, 'value' => 'д-р', 'observed_at' => now()]);

        $result = app(FacilityMerger::class)->merge($draft->id, $register->id, $this->admin);

        $this->assertSame(1, $result->counts['staff_entries_folded']);
        $this->assertNull(SourceRecord::query()->find($old->id));
        $this->assertSame($twin->id, (int) FieldProvenance::query()->where('subject_id', $doctor->id)->where('field', 'title')->value('source_record_id'));
        $this->assertTrue($twin->fresh()->last_seen_at->isSameDay(now()->addDay()));
    }

    public function test_a_doctor_whose_workplaces_are_locked_keeps_the_old_link(): void
    {
        $register = $this->register();
        $draft = $this->draft();
        $doctor = Doctor::factory()->create();
        $this->link($doctor, $draft);
        ProvenanceWriter::setLock($doctor, 'facilities', true, $this->admin);

        $result = app(FacilityMerger::class)->merge($draft->id, $register->id, $this->admin);

        $this->assertSame(1, $result->counts['doctors_locked']);
        $this->assertSame([$draft->id], DB::table('doctor_facility')->where('doctor_id', $doctor->id)->pluck('facility_id')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_merges_that_do_not_fit_are_refused_and_write_nothing(): void
    {
        $register = $this->register();
        $otherRegister = $this->register(['name' => 'ЈЗУ Друга Болница']);
        $draft = $this->draft();
        $otherDraft = $this->draft(['name' => 'ПЗУ Друга'], 'druga');
        $merger = app(FacilityMerger::class);

        $this->assertSame('source_not_website_only', $merger->merge($otherRegister->id, $register->id, $this->admin)->reason);
        $this->assertSame('target_not_register', $merger->merge($draft->id, $otherDraft->id, $this->admin)->reason);
        $this->assertSame('same_facility', $merger->merge($draft->id, $draft->id, $this->admin)->reason);

        Review::factory()->create(['reviewable_type' => Facility::class, 'reviewable_id' => $draft->id]);
        $this->assertSame('source_has_reviews', $merger->merge($draft->id, $register->id, $this->admin)->reason);
        $this->assertFalse($draft->fresh()->trashed());
        $this->assertSame(0, Activity::query()->where('log_name', FacilityMerger::LOG)->count());

        $merger->merge($otherDraft->id, $register->id, $this->admin);
        $this->assertSame('merged_elsewhere', $merger->merge($otherDraft->id, $otherRegister->id, $this->admin)->reason);
    }

    public function test_the_matcher_needs_two_independent_signals_for_a_high_confidence_proposal(): void
    {
        $register = $this->register();
        $this->register(['name' => 'ЈЗУ Универзитетска клиника за детски болести', 'address' => 'Ул.Водњанска БР.17']);
        $draft = $this->draft();
        // Name only (another address): medium.
        $saraDent = $this->register(['name' => 'ПЗУ Сара Дент', 'city' => 'Тетово', 'address' => 'Ул Браќа Миладиновци 97', 'type' => FacilityType::Clinic]);
        $saradent = $this->draft(['name' => 'Saradent', 'city' => 'Тетово', 'address' => 'Булевар Маршал Тито', 'phone' => null, 'website' => null, 'type' => FacilityType::Clinic], 'saradent');
        // Phone + town: high, whatever the name.
        $byPhone = $this->register(['name' => 'ПЗУ Ординација Здравје', 'city' => 'Битола', 'address' => 'Партизанска бб', 'phone' => '047 111 222', 'type' => FacilityType::Clinic]);
        $phoned = $this->draft(['name' => 'Клиника Нова Надеж', 'city' => 'Битола', 'address' => null, 'phone' => '+389 47 111 222', 'website' => null, 'type' => FacilityType::Clinic], 'nadez');
        // Two register facilities with the same name at one address: a person decides.
        $this->register(['name' => 'ПЗУ Дуо', 'city' => 'Струмица', 'address' => 'Ката Поцкова 85', 'type' => FacilityType::Clinic]);
        $this->register(['name' => 'ПЗУ Дуо', 'city' => 'Струмица', 'address' => 'Ул. Ката Поцкова бр. 85', 'type' => FacilityType::Clinic]);
        $duo = $this->draft(['name' => 'Duo', 'city' => 'Струмица', 'address' => 'Ката Поцкова 85', 'phone' => null, 'website' => null, 'type' => FacilityType::Clinic], 'duo');
        // Another town, nothing in common but the name: no proposal.
        $elsewhere = $this->draft(['name' => 'ПЗУ Сара Дент', 'city' => 'Охрид', 'address' => null, 'phone' => null, 'website' => null, 'type' => FacilityType::Clinic], 'sara-ohrid');

        $matcher = app(FacilityDuplicateMatcher::class)->load();

        $infectious = $matcher->match($draft);
        $this->assertSame('high', $infectious['confidence']);
        $this->assertSame($register->id, $infectious['suggested_facility_id']);
        $this->assertStringContainsString('same campus', implode('; ', $infectious['reasons']));

        $this->assertSame(['medium', $saraDent->id], [$matcher->match($saradent)['confidence'], $matcher->match($saradent)['suggested_facility_id']]);
        $this->assertSame(['high', $byPhone->id], [$matcher->match($phoned)['confidence'], $matcher->match($phoned)['suggested_facility_id']]);
        $duoProposal = $matcher->match($duo);
        $this->assertSame('medium', $duoProposal['confidence']);
        $this->assertStringContainsString('ambiguous', implode('; ', $duoProposal['reasons']));
        $this->assertNull($matcher->match($elsewhere)['confidence']);
        // Merged or deleted register facilities are never proposed; website drafts never are.
        $this->assertNotContains($saradent->id, array_column($matcher->match($elsewhere)['candidates'], 'facility_id'));
    }

    public function test_the_plan_merges_the_certain_ones_and_queues_the_rest_once(): void
    {
        $register = $this->register();
        $draft = $this->draft();
        $saraDent = $this->register(['name' => 'ПЗУ Сара Дент', 'city' => 'Тетово', 'address' => 'Ул Браќа Миладиновци 97', 'type' => FacilityType::Clinic]);
        $saradent = $this->draft(['name' => 'Saradent', 'city' => 'Тетово', 'address' => 'Булевар Маршал Тито', 'phone' => null, 'website' => null, 'type' => FacilityType::Clinic], 'saradent');
        $doctor = Doctor::factory()->create();
        $this->link($doctor, $draft);
        $path = sys_get_temp_dir().'/fmerge-plan-'.bin2hex(random_bytes(4)).'.json';

        // The dry run reads only.
        $counts = fn (): array => [Facility::withTrashed()->count(), DB::table('doctor_facility')->count(), ImportReviewItem::query()->count(), DB::table('import_runs')->count(), Activity::query()->count()];
        $before = $counts();
        $this->artisan('import:facility-duplicates', ['--json' => $path])->assertSuccessful();
        $this->assertSame($before, $counts());
        $plan = json_decode((string) file_get_contents($path), true);
        File::delete($path);
        $this->assertSame([$draft->id], array_column($plan['high'], 'facility_id'));
        $this->assertSame([$saradent->id], array_column($plan['medium'], 'facility_id'));
        $this->assertSame(1, $plan['counts']['would_get_visible_workplace_high']);

        $this->artisan('import:facility-duplicates', ['--apply' => true, '--by' => $this->admin->email])->assertSuccessful();

        $this->assertSame($register->id, Facility::withTrashed()->find($draft->id)?->merged_into_id);
        $item = ImportReviewItem::query()->where('item_key', FacilityDuplicatePlanner::ITEM_PREFIX.$saradent->id)->firstOrFail();
        $this->assertSame($saraDent->id, $item->details['suggested_facility_id']);
        $this->assertSame(ImportReviewStatus::Open, $item->status);
        $this->assertFalse($saradent->fresh()->trashed());

        // Once more: the merge is done, the item is refreshed, not doubled.
        $this->artisan('import:facility-duplicates', ['--apply' => true, '--by' => $this->admin->email])->assertSuccessful();
        $this->assertSame(1, ImportReviewItem::query()->where('item_key', 'like', FacilityDuplicatePlanner::ITEM_PREFIX.'%')->count());
        $this->assertSame(2, Activity::query()->where('log_name', FacilityMerger::LOG)->count());
    }

    public function test_a_plan_entry_that_no_longer_holds_is_queued_instead_of_merged(): void
    {
        $register = $this->register();
        $draft = $this->draft();
        $planner = app(FacilityDuplicatePlanner::class);
        $plan = $planner->plan();
        // The register facility moved since the plan was made.
        $register->forceFill(['address' => 'Ул. Друга 5', 'city' => 'Битола'])->save();

        $result = $planner->apply($plan, $this->admin);

        $this->assertSame(1, $result['changed_since_plan']);
        $this->assertFalse($draft->fresh()->trashed());
    }

    public function test_a_draft_that_was_published_is_never_merged_automatically(): void
    {
        $this->register();
        $draft = $this->draft();
        $draft->forceFill(['is_published' => true, 'published_at' => now()])->save();
        $planner = app(FacilityDuplicatePlanner::class);

        $result = $planner->apply($planner->plan(), $this->admin);

        $this->assertSame(1, $result['changed_since_plan']);
        $this->assertFalse($draft->fresh()->trashed());
    }

    public function test_the_next_import_of_the_site_updates_the_register_facility(): void
    {
        $dir = sys_get_temp_dir().'/fmerge-json-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($dir);
        $dataset = function (array $overrides = []) use ($dir): string {
            $institution = array_merge([
                'slug' => 'uk-infektivni',
                'name_mk' => 'ЈЗУ Универзитетска клиника за инфективни болести и фебрилни состојби – Скопје',
                'type' => 'university_clinic',
                'ownership' => 'public',
                'address' => 'ул. Мајка Тереза бр. 17, 1000 Скопје',
                'town' => 'Скопје',
                'phones' => ['+389 2 3228 224'],
                'website' => 'https://infektivnaklinika.mk',
                'sources' => ['https://infektivnaklinika.mk/tim'],
                'confidence' => 'high',
                'workers' => [
                    ['full_name' => 'Измислена Инфектолошка', 'title' => 'д-р', 'role' => 'physician', 'specialty' => 'Инфективни болести', 'source_url' => 'https://infektivnaklinika.mk/tim', 'confidence' => 'high'],
                ],
            ], $overrides);
            file_put_contents($dir.'/institutions.json', json_encode(['slice' => 'public-hospitals', 'institutions' => [$institution]], JSON_UNESCAPED_UNICODE));

            return $dir.'/institutions.json';
        };

        // The register facility exists under its abbreviated register name
        // and another address: the website import creates a draft.
        $register = $this->register();
        $this->artisan('import:institutions-json', ['path' => $dataset()])->assertSuccessful();
        $draft = Facility::query()->where('import_source', 'website')->sole();
        $doctor = Doctor::query()->where('full_name', 'Измислена Инфектолошка')->sole();

        $this->assertSame(FacilityMergeResult::MERGED, app(FacilityMerger::class)->merge($draft->id, $register->id, $this->admin)->status);

        // Unchanged, then changed: no new draft, the doctor stays at the register facility.
        $this->artisan('import:institutions-json', ['path' => $dataset()])->assertSuccessful();
        $this->artisan('import:institutions-json', ['path' => $dataset([
            'email' => 'info@infektivnaklinika.mk',
            'workers' => [
                ['full_name' => 'Измислена Инфектолошка', 'title' => 'проф. д-р', 'role' => 'physician', 'specialty' => 'Инфективни болести', 'source_url' => 'https://infektivnaklinika.mk/tim', 'confidence' => 'high'],
                ['full_name' => 'Нова Инфектолошка', 'role' => 'physician', 'specialty' => 'Инфективни болести', 'source_url' => 'https://infektivnaklinika.mk/tim', 'confidence' => 'high'],
            ],
        ])])->assertSuccessful();
        File::deleteDirectory($dir);

        $this->assertSame(0, Facility::query()->where('import_source', 'website')->count());
        $register->refresh();
        $this->assertSame('ЈЗУ Универ. Клин. за Инфек. Болес. и Фебрил. Состојби', $register->name);
        $this->assertSame('info@infektivnaklinika.mk', $register->email);
        $this->assertSame([$register->id], DB::table('doctor_facility')->where('doctor_id', $doctor->id)->pluck('facility_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame('проф. д-р', $doctor->fresh()->title);
        $new = Doctor::query()->where('full_name', 'Нова Инфектолошка')->sole();
        $this->assertSame([$register->id], DB::table('doctor_facility')->where('doctor_id', $new->id)->pluck('facility_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(0, SourceRecord::query()->where('external_key', 'like', 'worker:'.$draft->id.':%')->count());
        $this->assertSame(2, SourceRecord::query()->where('external_key', 'like', 'worker:'.$register->id.':%')->count());
        $this->assertSame(0, DB::table('doctor_facility')->where('doctor_id', $doctor->id)->where('facility_id', $draft->id)->count());
    }

    public function test_an_import_record_still_naming_a_merged_draft_follows_the_merge(): void
    {
        $register = $this->register();
        $draft = $this->draft();
        app(FacilityMerger::class)->merge($draft->id, $register->id, $this->admin);
        // An older record (another slice) that still names the draft.
        $record = SourceRecord::query()->create([
            'source' => 'website', 'external_key' => 'institution:other-slice:uk-infektivni', 'subject_type' => 'facility', 'subject_id' => $draft->id,
            'payload' => [], 'hash' => 'x', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
        $dir = sys_get_temp_dir().'/fmerge-json-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($dir);
        file_put_contents($dir.'/institutions.json', json_encode(['slice' => 'other-slice', 'institutions' => [[
            'slug' => 'uk-infektivni', 'name_mk' => 'ЈЗУ Универзитетска клиника за инфективни болести', 'town' => 'Скопје', 'type' => 'university_clinic',
            'workers' => [['full_name' => 'Трета Инфектолошка', 'role' => 'physician', 'confidence' => 'high']],
        ]]], JSON_UNESCAPED_UNICODE));

        $this->artisan('import:institutions-json', ['path' => $dir.'/institutions.json'])->assertSuccessful();
        File::deleteDirectory($dir);

        $this->assertSame($register->id, (int) $record->fresh()->subject_id);
        $third = Doctor::query()->where('full_name', 'Трета Инфектолошка')->sole();
        $this->assertSame([$register->id], DB::table('doctor_facility')->where('doctor_id', $third->id)->pluck('facility_id')->map(fn ($id): int => (int) $id)->all());
    }
}
