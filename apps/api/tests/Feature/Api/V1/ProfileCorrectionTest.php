<?php

namespace Tests\Feature\Api\V1;

use App\Actions\AnonymiseUser;
use App\Enums\FacilityType;
use App\Enums\ProfileCorrectionField;
use App\Enums\ProfileCorrectionStatus;
use App\Enums\ProfileCorrectionType;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ProfileCorrection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * „Пријави грешка во профилот“ and the listed doctor's objection or removal
 * request (research memo §2.1): anyone may send one, the answer targets are
 * fixed at receipt, and a honeypot or a flood stores nothing.
 */
class ProfileCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function correction(array $overrides = []): array
    {
        return [
            'type' => 'correction',
            'field' => 'office_hours',
            'message' => 'Во петок ординацијата работи до 14 часот, не до 18.',
            ...$overrides,
        ];
    }

    public function test_anyone_can_report_an_error_on_a_published_doctor_profile(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska']);

        $this->postJson('/api/v1/doctors/ana-petrovska/corrections', $this->correction(['contact' => 'pacient@example.test']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.message', __('api.profile_correction.received_correction'));

        $request = ProfileCorrection::query()->sole();
        $this->assertSame(ProfileCorrectionType::Correction, $request->type);
        $this->assertSame(ProfileCorrectionStatus::Open, $request->status);
        $this->assertSame(ProfileCorrectionField::OfficeHours, $request->field);
        $this->assertSame(Doctor::class, $request->subject_type);
        $this->assertSame($doctor->id, $request->subject_id);
        $this->assertSame('pacient@example.test', $request->contact);
        $this->assertNull($request->user_id);
        $this->assertSame('2026-11-04 10:00:00', $request->due_at->toDateTimeString());
    }

    public function test_a_signed_in_member_is_recorded_and_markup_is_stripped(): void
    {
        $doctor = Doctor::factory()->create();
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        $this->postJson("/api/v1/doctors/{$doctor->slug}/corrections", $this->correction([
            'message' => '<b>Погрешен</b> телефон на ординацијата.',
            'contact' => null,
        ]))->assertCreated();

        $request = ProfileCorrection::query()->sole();
        $this->assertSame($member->id, $request->user_id);
        $this->assertSame('Погрешен телефон на ординацијата.', $request->message);
        $this->assertNull($request->contact);
    }

    public function test_the_listed_doctor_can_object_with_a_contact_and_gets_thirty_days(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $doctor = Doctor::factory()->create();

        $this->postJson("/api/v1/doctors/{$doctor->slug}/corrections", [
            'type' => 'objection',
            'message' => 'Јас сум овој лекар и не сакам да бидам на листата.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['contact']);

        $this->postJson("/api/v1/doctors/{$doctor->slug}/corrections", [
            'type' => 'objection',
            'field' => 'name',
            'message' => 'Јас сум овој лекар и не сакам да бидам на листата.',
            'contact' => '070 123 456',
        ])->assertCreated()
            ->assertJsonPath('data.message', __('api.profile_correction.received_objection'));

        $request = ProfileCorrection::query()->sole();
        $this->assertSame(ProfileCorrectionType::Objection, $request->type);
        $this->assertNull($request->field);
        $this->assertSame('070 123 456', $request->contact);
        $this->assertSame('2026-11-19 10:00:00', $request->due_at->toDateTimeString());
    }

    public function test_facilities_take_corrections_from_their_own_list_but_not_objections(): void
    {
        $facility = Facility::factory()->create(['type' => FacilityType::Hospital]);

        $this->postJson("/api/v1/facilities/{$facility->slug}/corrections", $this->correction(['field' => 'departments']))
            ->assertCreated();
        $this->assertSame(Facility::class, ProfileCorrection::query()->sole()->subject_type);

        $this->postJson("/api/v1/facilities/{$facility->slug}/corrections", [
            'type' => 'objection',
            'message' => 'Барам да се отстрани профилот на установата.',
            'contact' => 'info@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors(['type']);

        $doctor = Doctor::factory()->create();
        $this->postJson("/api/v1/doctors/{$doctor->slug}/corrections", $this->correction(['field' => 'departments']))
            ->assertUnprocessable()->assertJsonValidationErrors(['field']);

        $this->assertSame(1, ProfileCorrection::query()->count());
    }

    public function test_only_published_profiles_can_be_the_subject(): void
    {
        $hidden = Doctor::factory()->unpublished()->create();
        $pharmacy = Facility::factory()->pharmacy()->create();
        $draft = Facility::factory()->unpublished()->create(['type' => FacilityType::Clinic]);

        $this->postJson("/api/v1/doctors/{$hidden->slug}/corrections", $this->correction())->assertNotFound();
        $this->postJson("/api/v1/facilities/{$pharmacy->slug}/corrections", $this->correction(['field' => 'name']))->assertNotFound();
        $this->postJson("/api/v1/facilities/{$draft->slug}/corrections", $this->correction(['field' => 'name']))->assertNotFound();
        $this->postJson('/api/v1/doctors/nema-takov/corrections', $this->correction())->assertNotFound();

        $this->assertSame(0, ProfileCorrection::query()->count());
    }

    public function test_the_fields_are_validated_with_macedonian_messages(): void
    {
        $doctor = Doctor::factory()->create();

        $response = $this->withHeader('Accept-Language', 'mk')
            ->postJson("/api/v1/doctors/{$doctor->slug}/corrections", [
                'field' => 'nepostoecko',
                'message' => str_repeat('а', 1001),
                'contact' => 'not-an-address',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['field', 'message', 'contact']);

        foreach (['field', 'message', 'contact'] as $key) {
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]{3,}/', (string) $response->json("errors.{$key}.0"), $key);
        }

        $this->postJson("/api/v1/doctors/{$doctor->slug}/corrections", $this->correction(['message' => 'кратко']))
            ->assertUnprocessable()->assertJsonValidationErrors(['message']);

        $this->assertSame(0, ProfileCorrection::query()->count());
    }

    public function test_a_filled_honeypot_is_answered_like_a_success_and_stores_nothing(): void
    {
        $doctor = Doctor::factory()->create();

        $this->postJson("/api/v1/doctors/{$doctor->slug}/corrections", $this->correction(['website' => 'http://spam.example']))
            ->assertCreated()
            ->assertJsonPath('data.message', __('api.profile_correction.received_correction'));

        // Even an otherwise invalid request gets the same answer, so a bot
        // learns nothing from the validation errors.
        $this->postJson("/api/v1/doctors/{$doctor->slug}/corrections", ['website' => 'x', 'message' => 'x'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'received');

        $this->assertSame(0, ProfileCorrection::query()->count());
    }

    public function test_one_address_can_send_only_a_few_requests_in_a_burst(): void
    {
        $doctor = Doctor::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/v1/doctors/{$doctor->slug}/corrections", $this->correction())->assertCreated();
        }

        $this->postJson("/api/v1/doctors/{$doctor->slug}/corrections", $this->correction())->assertStatus(429);

        // Another address is not affected.
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->postJson("/api/v1/doctors/{$doctor->slug}/corrections", $this->correction())
            ->assertCreated();

        $this->assertSame(6, ProfileCorrection::query()->count());
    }

    public function test_account_deletion_unlinks_the_request_and_drops_the_reply_address(): void
    {
        $doctor = Doctor::factory()->create();
        $member = User::factory()->create();
        $request = ProfileCorrection::query()->create([
            'type' => ProfileCorrectionType::Correction,
            'subject_type' => Doctor::class,
            'subject_id' => $doctor->id,
            'field' => ProfileCorrectionField::Contact,
            'message' => 'Телефонот е погрешен.',
            'contact' => 'clen@example.test',
            'user_id' => $member->id,
            'due_at' => now()->addDays(15),
        ]);

        app(AnonymiseUser::class)->handle($member);

        $request->refresh();
        $this->assertNull($request->user_id);
        $this->assertNull($request->contact);
        $this->assertSame('Телефонот е погрешен.', $request->message);
        $this->assertTrue($request->isOpen());
    }

    public function test_the_member_export_lists_their_requests_without_the_staff_note(): void
    {
        $doctor = Doctor::factory()->create(['slug' => 'ana-petrovska', 'full_name' => 'д-р Ана Петровска']);
        $member = User::factory()->create();
        ProfileCorrection::query()->create([
            'type' => ProfileCorrectionType::Correction,
            'subject_type' => Doctor::class,
            'subject_id' => $doctor->id,
            'field' => ProfileCorrectionField::Name,
            'message' => 'Презимето е погрешно напишано.',
            'contact' => 'clen@example.test',
            'user_id' => $member->id,
            'status' => ProfileCorrectionStatus::Resolved,
            'resolution_note' => 'Внатрешна белешка',
            'resolved_at' => now(),
            'due_at' => now()->addDays(15),
        ]);
        Sanctum::actingAs($member);

        $response = $this->get('/api/v1/me/export')->assertOk();
        $export = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $export['profile_corrections']);
        $row = $export['profile_corrections'][0];
        $this->assertSame(['kind' => 'doctor', 'name' => 'д-р Ана Петровска', 'slug' => 'ana-petrovska'], $row['about']);
        $this->assertSame('name', $row['field']);
        $this->assertSame('resolved', $row['status']);
        $this->assertStringNotContainsString('Внатрешна белешка', $response->streamedContent());
    }

    public function test_closed_requests_are_pruned_after_the_retention_period_and_open_ones_stay(): void
    {
        $doctor = Doctor::factory()->create();
        $make = fn (ProfileCorrectionStatus $status, ?Carbon $resolvedAt): ProfileCorrection => ProfileCorrection::query()->create([
            'type' => ProfileCorrectionType::Correction,
            'subject_type' => Doctor::class,
            'subject_id' => $doctor->id,
            'field' => ProfileCorrectionField::Other,
            'message' => 'Порака за проверка.',
            'status' => $status,
            'resolved_at' => $resolvedAt,
            'due_at' => now()->subDays(400),
        ]);

        $old = $make(ProfileCorrectionStatus::Resolved, now()->subDays(366));
        $recent = $make(ProfileCorrectionStatus::Declined, now()->subDays(300));
        $open = $make(ProfileCorrectionStatus::Open, null);

        $this->artisan('model:prune', ['--model' => [ProfileCorrection::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($open);
    }
}
