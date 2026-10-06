<?php

namespace Tests\Feature\Import;

use App\Enums\ImportReviewKind;
use App\Models\Doctor;
use App\Models\ImportReviewItem;
use App\Support\Import\Contracts\DoctorLicenceSink;
use App\Support\Import\Contracts\LicenceAttachResult;
use App\Support\Import\Contracts\LicenceRecord;
use App\Support\Import\Contracts\LicenceReviewReason;
use App\Support\Import\ProvenanceWriter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorLicenceSinkTest extends TestCase
{
    use RefreshDatabase;

    private function record(string $number = '0011032', string $until = '2031-11-20'): LicenceRecord
    {
        return new LicenceRecord(
            licenceNumber: $number,
            validUntil: CarbonImmutable::parse($until),
            fullName: 'ИЗМИСЛЕНА ЛИЧНОСТ',
            specialty: 'радиологија',
            observedAt: CarbonImmutable::parse('2026-07-02'),
        );
    }

    private function sink(): DoctorLicenceSink
    {
        return app(DoctorLicenceSink::class);
    }

    public function test_it_attaches_a_licence_once_and_is_idempotent(): void
    {
        $doctor = Doctor::factory()->create();

        $this->assertSame(LicenceAttachResult::Attached, $this->sink()->attach($doctor->getKey(), $this->record()));
        $this->assertSame(LicenceAttachResult::Unchanged, $this->sink()->attach($doctor->getKey(), $this->record()));

        $doctor->refresh();
        $this->assertSame('0011032', $doctor->licence_number);
        $this->assertSame('2031-11-20', $doctor->licence_valid_until?->toDateString());
        $this->assertSame('komora', $doctor->licence_source);
        $this->assertTrue($doctor->hasValidLicence());

        $this->assertSame(LicenceAttachResult::Attached, $this->sink()->attach($doctor->getKey(), $this->record(until: '2035-01-01')));
        $this->assertSame('2035-01-01', $doctor->refresh()->licence_valid_until?->toDateString());
    }

    public function test_a_number_held_by_another_doctor_is_not_moved(): void
    {
        $holder = Doctor::factory()->create();
        $other = Doctor::factory()->create();
        $this->sink()->attach($holder->getKey(), $this->record());

        $this->assertSame(LicenceAttachResult::Conflict, $this->sink()->attach($other->getKey(), $this->record()));
        $this->assertNull($other->refresh()->licence_number);
        $this->assertSame(1, ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Conflict)->count());

        // A doctor holding a different number is not overwritten either.
        $this->assertSame(LicenceAttachResult::Conflict, $this->sink()->attach($holder->getKey(), $this->record('0099999')));
        $this->assertSame('0011032', $holder->refresh()->licence_number);
    }

    public function test_a_locked_licence_is_left_alone(): void
    {
        $doctor = Doctor::factory()->create();
        ProvenanceWriter::setLock($doctor, 'licence', true, null);

        $this->assertSame(LicenceAttachResult::Locked, $this->sink()->attach($doctor->getKey(), $this->record()));
        $this->assertNull($doctor->refresh()->licence_number);
    }

    public function test_unknown_doctor_and_review_queue(): void
    {
        $this->assertSame(LicenceAttachResult::DoctorNotFound, $this->sink()->attach(999999, $this->record()));

        $this->sink()->queueForReview($this->record(), LicenceReviewReason::Ambiguous, [1, 2]);
        $this->sink()->queueForReview($this->record(), LicenceReviewReason::NoMatch);

        $items = ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Unmatched)->get();
        $this->assertCount(1, $items, 'One open item per licence number; a re-run refreshes it.');
        $this->assertSame('no_match', $items->first()?->details['reason']);

        $doctor = Doctor::factory()->create();
        $this->sink()->attach($doctor->getKey(), $this->record());
        $this->assertSame(0, ImportReviewItem::query()->open()->where('kind', ImportReviewKind::Unmatched)->count());
    }

    public function test_the_public_profile_shows_only_a_licence_status(): void
    {
        $doctor = Doctor::factory()->create(['is_published' => true]);
        $this->sink()->attach($doctor->getKey(), $this->record());

        $response = $this->getJson('/api/v1/doctors/'.$doctor->slug)->assertOk();

        $response->assertJsonPath('data.has_valid_licence', true);
        $this->assertStringNotContainsString('0011032', (string) $response->getContent());

        $doctor->forceFill(['licence_valid_until' => '2020-01-01'])->save();
        $this->getJson('/api/v1/doctors/'.$doctor->slug)->assertJsonPath('data.has_valid_licence', false);
    }
}
