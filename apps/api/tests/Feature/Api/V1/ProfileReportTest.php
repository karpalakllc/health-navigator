<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FacilityType;
use App\Enums\ProfileCorrectionStatus;
use App\Enums\ProfileCorrectionType;
use App\Enums\ProfileReportReason;
use App\Http\Controllers\Api\V1\ProfileReportController;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ProfileCorrection;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * „Пријави профил“ (W7-C): anyone can report a whole doctor, facility or
 * pharmacy profile. Reports join the corrections queue; repeats are absorbed
 * (a member: one open per profile; a guest: one per profile per day per
 * address) and the answer never changes. The guest's address is never
 * stored, only a keyed per-profile hash.
 */
class ProfileReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function report(string $path, array $body = [], string $ip = '203.0.113.7'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/'.$path.'/profile-reports', ['reason' => 'fake_profile', ...$body]);
    }

    public function test_a_guest_reports_a_doctor_profile_and_the_address_is_kept_only_as_a_hash(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);

        $this->report('doctors/ana-petrovska', ['reason' => 'wrong_person', 'note' => '  Ова е <b>друго</b> лице.  '])
            ->assertCreated()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.message', __('api.profile_report.received'));

        $report = ProfileCorrection::query()->sole();
        $this->assertSame(ProfileCorrectionType::Report, $report->type);
        $this->assertSame(ProfileReportReason::WrongPerson, $report->report_reason);
        $this->assertSame('Ова е друго лице.', $report->message);
        $this->assertSame(Doctor::class, $report->subject_type);
        $this->assertSame($doctor->id, $report->subject_id);
        $this->assertNull($report->user_id);
        $this->assertNull($report->field);
        $this->assertNull($report->contact);
        $this->assertSame(ProfileCorrectionStatus::Open, $report->status);
        $this->assertSame('2026-10-27 10:00:00', $report->due_at->toDateTimeString());
        $this->assertSame(ProfileReportController::guestHash('203.0.113.7', $doctor), $report->reporter_hash);
        $this->assertStringNotContainsString('203.0.113.7', json_encode($report->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_the_note_is_optional(): void
    {
        $doctor = Doctor::factory()->create();

        $this->report("doctors/{$doctor->slug}", ['reason' => 'no_longer_here'])->assertCreated();

        $this->assertNull(ProfileCorrection::query()->sole()->message);
    }

    public function test_a_guest_reports_a_profile_once_a_day_per_address(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $doctor = Doctor::factory()->create();
        $other = Doctor::factory()->create();

        $this->report("doctors/{$doctor->slug}")->assertCreated();
        // Same address, same profile, same day: the same answer, nothing new.
        $this->report("doctors/{$doctor->slug}", ['reason' => 'other'])->assertCreated();
        $this->assertSame(1, ProfileCorrection::query()->count());

        // Another profile, or another address: a new report.
        $this->report("doctors/{$other->slug}")->assertCreated();
        $this->report("doctors/{$doctor->slug}", ip: '198.51.100.20')->assertCreated();
        $this->assertSame(3, ProfileCorrection::query()->count());

        // A day later the same address may report again.
        Carbon::setTestNow('2026-10-21 10:00:01');
        $this->travelTo(now());
        $this->report("doctors/{$doctor->slug}")->assertCreated();
        $this->assertSame(4, ProfileCorrection::query()->count());
    }

    public function test_a_member_has_one_open_report_per_profile(): void
    {
        $doctor = Doctor::factory()->create();
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        $this->report("doctors/{$doctor->slug}")->assertCreated();
        $this->report("doctors/{$doctor->slug}", ip: '198.51.100.20')->assertCreated();

        $report = ProfileCorrection::query()->sole();
        $this->assertSame($member->id, $report->user_id);
        $this->assertNull($report->reporter_hash);

        // Once staff close it, the member may report the profile again.
        $report->forceFill(['status' => ProfileCorrectionStatus::Declined, 'resolved_at' => now()])->save();
        $this->report("doctors/{$doctor->slug}")->assertCreated();
        $this->assertSame(2, ProfileCorrection::query()->count());
    }

    public function test_facilities_and_pharmacies_are_reported_on_their_own_routes(): void
    {
        $clinic = Facility::factory()->create(['type' => FacilityType::Clinic]);
        $pharmacy = Facility::factory()->create(['type' => FacilityType::Pharmacy]);

        $this->report("facilities/{$clinic->slug}")->assertCreated();
        $this->report("pharmacies/{$pharmacy->slug}", ['reason' => 'inappropriate_content'])->assertCreated();

        // Each route knows only its own kind.
        $this->report("facilities/{$pharmacy->slug}", ip: '198.51.100.1')->assertNotFound();
        $this->report("pharmacies/{$clinic->slug}", ip: '198.51.100.1')->assertNotFound();

        $this->assertEqualsCanonicalizing(
            [$clinic->id, $pharmacy->id],
            ProfileCorrection::query()->pluck('subject_id')->all(),
        );
        $this->assertSame('pharmacy', ProfileCorrection::query()->where('subject_id', $pharmacy->id)->sole()->subjectKind());
    }

    public function test_only_published_profiles_can_be_reported(): void
    {
        $draft = Doctor::factory()->create(['is_published' => false]);

        $this->report("doctors/{$draft->slug}")->assertNotFound()->assertJsonPath('code', 'errors.not_found');
        $this->report('doctors/no-such-doctor')->assertNotFound();
        $this->assertSame(0, ProfileCorrection::query()->count());
    }

    public function test_the_reason_and_note_are_validated(): void
    {
        $doctor = Doctor::factory()->create();

        $this->report("doctors/{$doctor->slug}", ['reason' => 'spam'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
        $this->report("doctors/{$doctor->slug}", ['reason' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
        $this->report("doctors/{$doctor->slug}", ['note' => str_repeat('а', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);

        $this->assertSame(0, ProfileCorrection::query()->count());
    }

    public function test_a_filled_honeypot_is_answered_like_a_success_and_stores_nothing(): void
    {
        $doctor = Doctor::factory()->create();

        $this->report("doctors/{$doctor->slug}", ['website' => 'http://spam.example'])->assertCreated();
        $this->report("doctors/{$doctor->slug}", ['reason' => 'nonsense', 'website' => 'x'])->assertCreated();

        $this->assertSame(0, ProfileCorrection::query()->count());
    }

    public function test_one_address_can_send_only_a_few_reports_in_a_burst(): void
    {
        $doctors = Doctor::factory()->count(6)->create();

        foreach ($doctors->take(5) as $doctor) {
            $this->report("doctors/{$doctor->slug}")->assertCreated();
        }

        $this->report("doctors/{$doctors->last()->slug}")->assertStatus(429);
        $this->assertSame(5, ProfileCorrection::query()->count());
    }

    public function test_the_pharmacy_route_is_closed_while_the_module_is_off(): void
    {
        $pharmacy = Facility::factory()->create(['type' => FacilityType::Pharmacy]);
        SiteSetting::current()->forceFill(['public_pharmacies' => false])->save();

        $this->report("pharmacies/{$pharmacy->slug}")->assertStatus(503);
        $this->assertSame(0, ProfileCorrection::query()->count());
    }
}
