<?php

namespace Tests\Feature\Import;

use App\Console\Commands\ImportFzomExcludeAssociatesCommand;
use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Enums\ImportRunStatus;
use App\Models\Doctor;
use App\Models\ImportReviewItem;
use App\Models\ImportRun;
use App\Models\User;
use App\Support\Import\Fzom\FzomImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Health associates (facsimile 8xxxxx) are not imported as doctors
 * (docs/data-import.md §3a).
 */
class FzomAssociatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['import.disk' => 'local', 'import.max_missing_ratio' => 0.9, 'import.missing_after_runs' => 2]);
    }

    private function row(string $facsimile, string $surname, string $specialties, int $type = 9, string $activity = 'ИНТЕРНА МЕДИЦИНА'): string
    {
        return sprintf(
            '<Lekar><TipDogovor>Договор</TipDogovor><TipDogovorID>%d</TipDogovorID><DanocenBroj>4000000000555</DanocenBroj>'
            .'<ShifraZU>9000555</ShifraZU><ZdravstvenaUstanova>ПЗУ ЛАБОРАТОРИЈА ТЕСТ</ZdravstvenaUstanova><RabotnaEdinica>ЛАБОРАТОРИЈА</RabotnaEdinica>'
            .'<Dejnost>%s</Dejnost><Specijalnosti>%s</Specijalnosti><Adresa>УЛ. ПРОБНА 1</Adresa><Mesto>ПРОБНО</Mesto>'
            .'<Faksimil>%s</Faksimil><Ime>ИМЕ</Ime><Prezime>%s</Prezime><ValidenOd>2025-01-01T00:00:00</ValidenOd></Lekar>',
            $type, $activity, $specialties, $facsimile, $surname,
        );
    }

    /**
     * @return array<string, string>
     */
    private function files(): array
    {
        $rows = implode('', [
            $this->row('800001', 'БИОЛОГОВА', 'ЛАБОРАТОРИСКИ БИОЛОГ'),
            $this->row('800002', 'ЛАБОРАТОРИСКА', ''),
            $this->row('800003', 'ГЕНЕТИЧАРОВА', 'МЕДИЦИНСКА ГЕНЕТИКА'),
            $this->row('800004', 'ЛЕКАРОВА', 'ОПШТА МЕДИЦИНА'),
            $this->row('700001', 'ФИЗИЧАРОВА', 'ИНТЕРНА МЕДИЦИНА', 16),
        ]);
        $make = function (string $content): string {
            $path = tempnam(sys_get_temp_dir(), 'fzom').'.xml';
            file_put_contents($path, '<?xml version="1.0" encoding="utf-8"?><Lekari>'.$content.'</Lekari>');

            return $path;
        };

        return ['pzz' => $make(''), 'spec' => $make($rows)];
    }

    private function import(): ImportRun
    {
        return app(FzomImportJob::class)->run(false, null, $this->files());
    }

    public function test_associates_are_excluded_and_the_exception_is_a_hidden_draft_with_a_review_item(): void
    {
        $run = $this->import();

        $this->assertSame(ImportRunStatus::Succeeded, $run->status, (string) $run->error);
        $this->assertSame(3, $run->count('people_excluded_associate'));
        $this->assertSame(3, $run->count('people_excluded_non_physician'));
        $this->assertSame(1, $run->count('associates_with_physician_specialty'));
        $this->assertSame(0, $run->count('doctors_specialty_from_activity'));

        $this->assertEqualsCanonicalizing(['700001', '800004'], Doctor::query()->pluck('fzo_facsimile')->all());

        $physician = Doctor::query()->where('fzo_facsimile', '700001')->firstOrFail();
        $this->assertSame(['interna-medicina'], $physician->specialties->pluck('slug')->all());

        $exception = Doctor::query()->where('fzo_facsimile', '800004')->firstOrFail();
        $this->assertFalse((bool) $exception->is_published);
        $this->assertSame(['opshta-medicina'], $exception->specialties->pluck('slug')->all());
        $item = ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Uncertain)->where('subject_id', $exception->getKey())->firstOrFail();
        $this->assertSame('associate_code_physician_specialty', $item->details['reason']);
        $this->assertSame(0, ImportReviewItem::query()->where('subject_id', $physician->getKey())->where('kind', ImportReviewKind::Uncertain)->count());
    }

    public function test_a_second_run_changes_nothing_and_queues_no_missing_items(): void
    {
        $this->import();
        $second = $this->import();
        $third = $this->import();

        $this->assertSame(0, $second->count('doctors_created'));
        $this->assertSame(2, $second->count('doctors_unchanged'));
        $this->assertSame(1, ImportReviewItem::query()->where('kind', ImportReviewKind::Uncertain)->count());
        $this->assertSame(0, $third->count('doctors_absent'));
        $this->assertSame(0, ImportReviewItem::query()->where('kind', ImportReviewKind::Missing)->count());
    }

    public function test_existing_associate_profiles_never_become_missing_items(): void
    {
        // Created by an earlier import, before the rule existed.
        config(['import.fzom.associate_facsimile_prefixes' => []]);
        $before = $this->import();
        $this->assertSame(5, $before->count('doctors_created'));
        config(['import.fzom.associate_facsimile_prefixes' => ['8']]);

        $this->import();
        $this->import();
        $run = $this->import();

        $this->assertSame(5, Doctor::query()->count(), 'Nothing is deleted.');
        $this->assertSame(0, $run->count('doctors_absent'));
        $this->assertSame(0, ImportReviewItem::query()->where('kind', ImportReviewKind::Missing)->count());
        $this->assertSame(0, Doctor::query()->where('import_missing_runs', '>', 0)->count());
    }

    public function test_a_licensed_profile_on_an_associate_code_stays_a_doctor_and_is_never_missing(): void
    {
        config(['import.fzom.associate_facsimile_prefixes' => []]);
        $this->import();
        config(['import.fzom.associate_facsimile_prefixes' => ['8']]);
        $licensed = Doctor::query()->where('fzo_facsimile', '800002')->firstOrFail();
        $licensed->forceFill(['licence_number' => '12600'])->save();

        $this->import();
        $this->import();
        $run = $this->import();

        $this->assertSame(2, $run->count('people_excluded_associate'), '800001 and 800003 (genetics is open to non-physicians); 800002 holds a licence, 800004 is the physician-specialty exception.');
        $this->assertSame(0, $run->count('doctors_absent'));
        $this->assertSame(0, ImportReviewItem::query()->where('kind', ImportReviewKind::Missing)->count());
        $this->assertSame(0, $licensed->refresh()->import_missing_runs);
    }

    public function test_the_exclude_command_closes_review_items_only_on_plain_associate_profiles(): void
    {
        config(['import.fzom.associate_facsimile_prefixes' => []]);
        $this->import();
        config(['import.fzom.associate_facsimile_prefixes' => ['8']]);

        Permission::findOrCreate('imports.manage', 'web');
        $staff = User::factory()->create();
        $staff->givePermissionTo('imports.manage');

        $licensed = Doctor::query()->where('fzo_facsimile', '800003')->firstOrFail();
        $licensed->forceFill(['licence_number' => '12600'])->save();

        $this->artisan('import:fzom-exclude-associates', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(7, ImportReviewItem::query()->open()->count(), '5 new drafts + facility + the unmapped wording.');

        $this->artisan('import:fzom-exclude-associates')->assertFailed();
        $this->artisan('import:fzom-exclude-associates', ['--by' => $staff->email])->assertSuccessful();

        $closed = ImportReviewItem::query()->where('status', ImportReviewStatus::Dismissed)->where('resolution', ImportFzomExcludeAssociatesCommand::RESOLUTION);
        $this->assertSame(2, $closed->count(), 'Only 800001 and 800002: 800003 has a licence, 800004 a physician specialty, 700001 is a physician.');
        $this->assertSame($staff->getKey(), $closed->first()->resolved_by_id);
        $this->assertSame(5, ImportReviewItem::query()->open()->count());

        // Idempotent.
        $this->artisan('import:fzom-exclude-associates', ['--by' => $staff->email])->assertSuccessful();
        $this->assertSame(2, ImportReviewItem::query()->where('resolution', ImportFzomExcludeAssociatesCommand::RESOLUTION)->count());
        $this->assertSame(5, Doctor::query()->count());
    }
}
