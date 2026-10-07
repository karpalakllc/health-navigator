<?php

namespace Tests\Feature\Licences;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Models\Doctor;
use App\Models\ImportReviewItem;
use App\Models\KomoraLicence;
use App\Models\Specialty;
use App\Support\Import\Contracts\DoctorLicenceSink;
use App\Support\Import\NameKey;
use App\Support\Licences\Contracts\LicenceCandidateSource;
use App\Support\Licences\KomoraLicenceImporter;
use App\Support\Licences\SpecialtyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The verification engine re-decides the unattached licences of the list
 * every night: a website draft created after the list arrived gets its
 * licence, and rows nobody needs to look at leave the review queue.
 */
class LicenceRematchTest extends TestCase
{
    use RefreshDatabase;

    private function staged(string $name, string $specialty, string $number, string $outcome = 'no_match'): KomoraLicence
    {
        return KomoraLicence::query()->create([
            'licence_number' => $number, 'full_name' => mb_strtoupper($name), 'name_key' => NameKey::for($name),
            'specialty' => $specialty, 'specialty_key' => SpecialtyKey::for($specialty),
            'valid_until' => '2030-01-01', 'list_date' => '2026-07-02',
            'first_seen_at' => now(), 'last_seen_at' => now(), 'outcome' => $outcome,
        ]);
    }

    private function rematch(): array
    {
        return (new KomoraLicenceImporter(app(LicenceCandidateSource::class), app(DoctorLicenceSink::class)))->rematchStaged();
    }

    public function test_a_later_website_draft_gets_its_licence_and_staging_only_rows_leave_the_queue(): void
    {
        $draft = Doctor::factory()->unpublished()->create(['full_name' => 'Ана Подоцнежна']);
        $draft->forceFill(['import_source' => 'website'])->saveQuietly();
        $draft->specialties()->attach(Specialty::query()->create(['slug' => 'pedijatar-test', 'name' => 'ПЕДИЈАТАР'])->id, ['is_primary' => true]);
        $matched = $this->staged('Ана Подоцнежна', 'педијатрија', '0007001');
        $nobody = $this->staged('Бобан Никаде', 'педијатрија', '0007002');

        // Left over from the first licence import, which queued every row.
        $legacy = ImportReviewItem::raise('komora', ImportReviewKind::Unmatched, 'licence:0007002', 'Licence 0007002: no match', ['reason' => 'no_match']);

        $counts = $this->rematch();

        $this->assertSame(1, $counts['attached']);
        $this->assertSame(1, $counts['no_match']);
        $this->assertSame('0007001', $draft->fresh()->licence_number);
        $this->assertSame($draft->id, $matched->fresh()->doctor_id);
        $this->assertSame('attached', $matched->fresh()->outcome);
        $this->assertSame('no_match', $nobody->fresh()->outcome);
        $this->assertSame(ImportReviewStatus::Resolved, $legacy->fresh()->status);
        $this->assertSame('staging_only', $legacy->fresh()->resolution);
        $this->assertSame(0, ImportReviewItem::query()->open()->count());

        // Idempotent.
        $this->assertSame(0, $this->rematch()['attached']);
    }
}
