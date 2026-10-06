<?php

namespace Tests\Feature\Api\V1;

use App\Actions\AnonymiseUser;
use App\Actions\DoctorAccount\AssignDoctorOwner;
use App\Actions\DoctorAccount\DecideDoctorChangeRequest;
use App\Enums\DoctorChangeRequestStatus;
use App\Enums\FacilityType;
use App\Enums\RemovalCategory;
use App\Enums\ReviewResponseStatus;
use App\Enums\UserKind;
use App\Mail\DoctorChangeRequestDecidedMail;
use App\Models\Doctor;
use App\Models\DoctorChangeRequest;
use App\Models\DoctorClaimRequest;
use App\Models\Facility;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * „Мој профил“: a member account staff linked to a doctor profile edits the
 * practice details at once, asks staff to change the sensitive ones, and
 * replies (pre-moderated) to the profile's reviews. Nothing else.
 */
class DoctorDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        $this->forgetRateLimits();
        Mail::fake();
        $this->staff = User::factory()->admin()->create();
    }

    /**
     * @return array{0: Doctor, 1: User, 2: string}
     */
    private function linkedDoctor(array $doctorAttributes = []): array
    {
        $doctor = Doctor::factory()->create([
            'slug' => 'ana-petrovska',
            'full_name' => 'д-р Ана Петровска',
            'phone' => '02 111 111',
            ...$doctorAttributes,
        ]);
        $account = User::factory()->create(['user_kind' => UserKind::Client]);
        app(AssignDoctorOwner::class)->handle($doctor, $account, $this->staff);

        return [$doctor->fresh(), $account, $account->createToken('web')->plainTextToken];
    }

    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function review(Doctor $doctor, array $attributes = []): Review
    {
        return Review::factory()->approved()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => $doctor->id,
            ...$attributes,
        ]);
    }

    public function test_me_names_the_managed_profile_and_null_for_everyone_else(): void
    {
        [, , $token] = $this->linkedDoctor();
        $other = User::factory()->create()->createToken('web')->plainTextToken;

        $this->as($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.managed_doctor.slug', 'ana-petrovska')
            ->assertJsonPath('data.user.managed_doctor.full_name', 'д-р Ана Петровска');

        $this->as($other)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.managed_doctor', null);
    }

    public function test_an_unlinked_account_gets_404_on_every_doctor_endpoint(): void
    {
        $this->linkedDoctor();
        $review = $this->review(Doctor::query()->firstOrFail());
        $token = User::factory()->create()->createToken('web')->plainTextToken;

        $this->as($token)->getJson('/api/v1/me/doctor')->assertNotFound()->assertJsonPath('code', 'doctor_account.not_linked');
        $this->as($token)->patchJson('/api/v1/me/doctor', ['phone' => '070 000 000'])->assertNotFound();
        $this->as($token)->postJson('/api/v1/me/doctor/change-requests', ['full_name' => 'Друго Име'])->assertNotFound();
        $this->as($token)->getJson('/api/v1/me/doctor/reviews')->assertNotFound();
        $this->as($token)->putJson("/api/v1/me/doctor/reviews/{$review->id}/reply", ['body' => 'Благодарам.'])->assertNotFound();

        $this->assertNull($review->fresh()->response_body);
        $this->assertSame('02 111 111', Doctor::query()->firstOrFail()->phone);
    }

    public function test_the_dashboard_shows_the_profile_and_its_stats(): void
    {
        [$doctor, , $token] = $this->linkedDoctor();
        $this->review($doctor, ['rating' => 4]);
        $this->review($doctor, ['rating' => 2]);

        $this->as($token)->getJson('/api/v1/me/doctor')
            ->assertOk()
            ->assertJsonPath('data.doctor.slug', 'ana-petrovska')
            ->assertJsonPath('data.doctor.phone', '02 111 111')
            ->assertJsonPath('data.stats.review_count', 2)
            ->assertJsonPath('data.stats.unanswered_reviews', 2)
            ->assertJsonPath('data.settings.replies_require_moderation', true)
            ->assertJsonPath('data.pending_change_request', null)
            ->assertJsonMissingPath('data.doctor.is_featured')
            ->assertJsonMissingPath('data.doctor.is_sponsored');
    }

    public function test_practice_details_save_at_once_as_plain_text(): void
    {
        [$doctor, , $token] = $this->linkedDoctor();

        $this->as($token)->patchJson('/api/v1/me/doctor', [
            'phone' => '070 123 456',
            'bio' => "<b>Кардиолог</b> со искуство.\r\n\r\n\r\nПрием по договор.",
            'accepts_new_patients' => false,
            'office_hours' => ['Пон' => '08:00–14:00', 'Пет' => ' 12:00–18:00 '],
        ])
            ->assertOk()
            ->assertJsonPath('data.doctor.phone', '070 123 456');

        $doctor->refresh();
        $this->assertSame('070 123 456', $doctor->phone);
        $this->assertSame("Кардиолог со искуство.\n\nПрием по договор.", $doctor->bio);
        $this->assertFalse($doctor->accepts_new_patients);
        $this->assertSame(['Пон' => '08:00–14:00', 'Пет' => '12:00–18:00'], $doctor->office_hours);

        // The public profile shows the new phone straight away.
        $this->getJson('/api/v1/doctors/ana-petrovska')->assertJsonPath('data.phone', '070 123 456');
    }

    public function test_the_doctor_can_never_change_staff_controlled_or_sensitive_fields_through_patch(): void
    {
        [$doctor, , $token] = $this->linkedDoctor(['is_featured' => false, 'is_sponsored' => false, 'is_published' => true]);

        $this->as($token)->patchJson('/api/v1/me/doctor', [
            'phone' => '070 999 999',
            'is_featured' => true,
            'is_sponsored' => true,
            'is_published' => false,
            'slug' => 'nov-slug',
            'full_name' => 'Сосема Друго Име',
            'owner_user_id' => null,
        ])->assertOk();

        $doctor->refresh();
        $this->assertSame('070 999 999', $doctor->phone);
        $this->assertFalse($doctor->is_featured);
        $this->assertFalse($doctor->is_sponsored);
        $this->assertTrue($doctor->is_published);
        $this->assertSame('ana-petrovska', $doctor->slug);
        $this->assertSame('д-р Ана Петровска', $doctor->full_name);
        $this->assertNotNull($doctor->owner_user_id);
    }

    public function test_office_hours_only_take_days_of_the_week(): void
    {
        [, , $token] = $this->linkedDoctor();

        $this->as($token)->patchJson('/api/v1/me/doctor', ['office_hours' => ['Mon' => '08–14']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['office_hours']);
    }

    public function test_the_photo_is_stored_through_the_image_pipeline(): void
    {
        Storage::fake('public');
        config(['media.disk' => 'public']);
        [$doctor, , $token] = $this->linkedDoctor();

        $this->as($token)->post('/api/v1/me/doctor/avatar', [
            'avatar' => UploadedFile::fake()->image('photo.jpg', 600, 600),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = (string) $doctor->fresh()->avatar_url;
        $this->assertStringEndsWith('.webp', $path);
        $this->assertStringContainsString('/doctors/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_name_change_waits_for_staff_and_the_profile_keeps_the_old_name(): void
    {
        [$doctor, $account, $token] = $this->linkedDoctor();

        $this->as($token)->postJson('/api/v1/me/doctor/change-requests', [
            'full_name' => 'д-р Ана Петровска-Ристовска',
            'title' => 'проф. д-р',
            'message' => 'Ново презиме по брак.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.change_request.status', 'pending')
            ->assertJsonPath('data.change_request.changes.full_name.old', 'д-р Ана Петровска')
            ->assertJsonPath('data.change_request.changes.full_name.new', 'д-р Ана Петровска-Ристовска');

        $this->assertSame('д-р Ана Петровска', $doctor->fresh()->full_name);
        $this->getJson('/api/v1/doctors/ana-petrovska')->assertJsonPath('data.full_name', 'д-р Ана Петровска');

        $request = DoctorChangeRequest::query()->sole();
        $this->assertSame($account->id, $request->user_id);
        $this->assertSame(['full_name', 'title'], array_keys($request->changes));

        $this->as($token)->getJson('/api/v1/me/doctor')
            ->assertJsonPath('data.pending_change_request.id', $request->id);

        // Staff approve: the public profile shows the new name, the doctor is told.
        app(DecideDoctorChangeRequest::class)->approve($request, $this->staff);

        $this->assertSame(DoctorChangeRequestStatus::Approved, $request->fresh()->status);
        $this->getJson('/api/v1/doctors/ana-petrovska')
            ->assertJsonPath('data.full_name', 'д-р Ана Петровска-Ристовска')
            ->assertJsonPath('data.title', 'проф. д-р');
        Mail::assertQueued(DoctorChangeRequestDecidedMail::class, fn (DoctorChangeRequestDecidedMail $mail): bool => $mail->approved
            && $mail->hasTo($account->email));
    }

    public function test_specialty_and_workplace_changes_are_requests_too(): void
    {
        [$doctor, , $token] = $this->linkedDoctor();
        $cardiology = Specialty::factory()->create(['name' => 'Кардиологија', 'is_published' => true]);
        $internal = Specialty::factory()->create(['name' => 'Интерна', 'is_published' => true]);
        $doctor->specialties()->attach($cardiology->id, ['is_primary' => true]);
        $clinic = Facility::factory()->create(['type' => FacilityType::Hospital, 'is_published' => true]);

        $this->as($token)->postJson('/api/v1/me/doctor/change-requests', [
            'specialty_ids' => [$cardiology->id, $internal->id],
            'primary_specialty_id' => $internal->id,
            'facility_ids' => [$clinic->id],
        ])->assertCreated();

        $this->assertSame([$cardiology->id], $doctor->fresh()->specialties->pluck('id')->all());

        app(DecideDoctorChangeRequest::class)->approve(DoctorChangeRequest::query()->sole(), $this->staff);

        $doctor->refresh()->load(['specialties', 'facilities']);
        $this->assertEqualsCanonicalizing([$cardiology->id, $internal->id], $doctor->specialties->pluck('id')->all());
        $this->assertSame($internal->id, $doctor->specialties->firstWhere('pivot.is_primary', true)?->id);
        $this->assertSame([$clinic->id], $doctor->facilities->pluck('id')->all());
    }

    public function test_the_dashboard_lists_only_its_own_workplaces_and_finds_others_by_name(): void
    {
        [$doctor, , $token] = $this->linkedDoctor();
        $own = Facility::factory()->create(['name' => 'Клиника Центар', 'type' => FacilityType::Clinic]);
        $requested = Facility::factory()->create(['name' => 'Болница Охрид', 'type' => FacilityType::Hospital]);
        Facility::factory()->count(3)->create(['type' => FacilityType::Clinic]);
        $other = Facility::factory()->create(['name' => 'Поликлиника Битола', 'type' => FacilityType::Clinic]);
        Facility::factory()->unpublished()->create(['name' => 'Поликлиника Скриена', 'type' => FacilityType::Clinic]);
        Facility::factory()->pharmacy()->create(['name' => 'Аптека Поликлиника']);
        $doctor->facilities()->attach($own->id, ['is_primary' => true]);

        $this->as($token)->getJson('/api/v1/me/doctor')
            ->assertOk()
            ->assertJsonPath('data.options.facilities.*.id', [$own->id]);

        $this->as($token)->postJson('/api/v1/me/doctor/change-requests', [
            'facility_ids' => [$own->id, $requested->id],
        ])->assertCreated();

        // Names for what the pending request asks for, still nothing else.
        $this->as($token)->getJson('/api/v1/me/doctor')
            ->assertJsonPath('data.options.facilities.*.id', [$requested->id, $own->id]);

        $this->as($token)->getJson('/api/v1/me/doctor/facilities?q='.urlencode('поликлиника'))
            ->assertOk()
            ->assertExactJson(['data' => [['id' => $other->id, 'name' => 'Поликлиника Битола', 'city' => $other->city]]]);
        // Script-insensitive, as the public search.
        $this->as($token)->getJson('/api/v1/me/doctor/facilities?q=poliklinika')
            ->assertJsonPath('data.*.id', [$other->id]);
        $this->as($token)->getJson('/api/v1/me/doctor/facilities?q=п')->assertUnprocessable();
    }

    public function test_only_a_linked_account_searches_workplaces(): void
    {
        $this->getJson('/api/v1/me/doctor/facilities?q=klinika')->assertUnauthorized();

        $member = User::factory()->create();

        $this->as($member->createToken('web')->plainTextToken)
            ->getJson('/api/v1/me/doctor/facilities?q=klinika')
            ->assertNotFound();
    }

    public function test_one_pending_request_at_a_time_and_no_empty_requests(): void
    {
        [, , $token] = $this->linkedDoctor();

        $this->as($token)->postJson('/api/v1/me/doctor/change-requests', ['full_name' => 'д-р Ана Петровска'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'doctor_account.no_changes');

        $this->as($token)->postJson('/api/v1/me/doctor/change-requests', ['title' => 'прим. д-р'])->assertCreated();
        $this->as($token)->postJson('/api/v1/me/doctor/change-requests', ['city' => 'Тестоград'])
            ->assertConflict()
            ->assertJsonPath('code', 'doctor_account.change_request_pending');

        $id = DoctorChangeRequest::query()->sole()->id;
        $this->as($token)->deleteJson("/api/v1/me/doctor/change-requests/{$id}")->assertOk();
        $this->assertSame(DoctorChangeRequestStatus::Withdrawn, DoctorChangeRequest::query()->sole()->status);
    }

    public function test_a_rejection_needs_a_reason_keeps_the_profile_and_mails_the_reason(): void
    {
        [$doctor, $account, $token] = $this->linkedDoctor();
        $this->as($token)->postJson('/api/v1/me/doctor/change-requests', ['title' => 'академик'])->assertCreated();
        $request = DoctorChangeRequest::query()->sole();

        app(DecideDoctorChangeRequest::class)->reject($request, $this->staff, 'Титулата не е потврдена во регистарот.');

        $this->assertSame(DoctorChangeRequestStatus::Rejected, $request->fresh()->status);
        $this->assertNotSame('академик', $doctor->fresh()->title);
        Mail::assertQueued(DoctorChangeRequestDecidedMail::class, fn (DoctorChangeRequestDecidedMail $mail): bool => ! $mail->approved
            && $mail->reason === 'Титулата не е потврдена во регистарот.'
            && $mail->hasTo($account->email));

        $this->as($token)->getJson('/api/v1/me/doctor')
            ->assertJsonPath('data.recent_change_requests.0.status', 'rejected')
            ->assertJsonPath('data.recent_change_requests.0.rejection_reason', 'Титулата не е потврдена во регистарот.');
    }

    public function test_a_doctor_reply_is_hidden_until_staff_approve_it(): void
    {
        [$doctor, , $token] = $this->linkedDoctor();
        $review = $this->review($doctor);

        $this->as($token)->putJson("/api/v1/me/doctor/reviews/{$review->id}/reply", [
            'body' => '<i>Благодарам</i> на повратната информација.',
        ])
            ->assertOk()
            ->assertJsonPath('data.review.reply.status', 'pending')
            ->assertJsonPath('data.review.reply.source', 'doctor')
            ->assertJsonPath('data.review.reply.body', 'Благодарам на повратната информација.');

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')->assertJsonPath('data.0.response', null);

        $review->refresh()->approveDoctorReply($this->staff);

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertJsonPath('data.0.response.body', 'Благодарам на повратната информација.')
            ->assertJsonPath('data.0.response.source', 'doctor')
            ->assertJsonPath('data.0.response.responder_name', 'д-р Ана Петровска');

        // An edit goes back to waiting; the public sees nothing unreviewed.
        $this->as($token)->putJson("/api/v1/me/doctor/reviews/{$review->id}/reply", ['body' => 'Изменет одговор.'])
            ->assertOk()
            ->assertJsonPath('data.review.reply.status', 'pending');
        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')->assertJsonPath('data.0.response', null);

        $this->as($token)->deleteJson("/api/v1/me/doctor/reviews/{$review->id}/reply")->assertOk();
        $this->assertNull($review->fresh()->response_body);
    }

    public function test_with_moderation_off_a_doctor_reply_publishes_at_once(): void
    {
        SiteSetting::current()->update(['doctor_replies_require_moderation' => false]);
        [$doctor, , $token] = $this->linkedDoctor();
        $review = $this->review($doctor);

        $this->as($token)->putJson("/api/v1/me/doctor/reviews/{$review->id}/reply", ['body' => 'Благодарам.'])
            ->assertOk()
            ->assertJsonPath('data.review.reply.status', 'approved');

        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')->assertJsonPath('data.0.response.source', 'doctor');
    }

    public function test_a_staff_response_is_not_the_doctors_to_overwrite(): void
    {
        [$doctor, , $token] = $this->linkedDoctor();
        $review = $this->review($doctor);
        $review->respond($this->staff, 'Одговор внесен од тимот.');

        $this->as($token)->getJson('/api/v1/me/doctor/reviews')
            ->assertJsonPath('data.0.can_reply', false)
            ->assertJsonPath('data.0.reply.source', 'staff');

        $this->as($token)->putJson("/api/v1/me/doctor/reviews/{$review->id}/reply", ['body' => 'Мој одговор.'])
            ->assertConflict()
            ->assertJsonPath('code', 'doctor_account.reply_staff_exists');
        $this->as($token)->deleteJson("/api/v1/me/doctor/reviews/{$review->id}/reply")->assertNotFound();

        $this->assertSame('Одговор внесен од тимот.', $review->fresh()->response_body);
    }

    public function test_another_doctors_review_and_unpublished_reviews_are_out_of_reach(): void
    {
        [, , $token] = $this->linkedDoctor();
        $otherDoctor = Doctor::factory()->create();
        $foreign = $this->review($otherDoctor);
        $pending = Review::factory()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => Doctor::query()->where('slug', 'ana-petrovska')->value('id'),
        ]);

        $this->as($token)->putJson("/api/v1/me/doctor/reviews/{$foreign->id}/reply", ['body' => 'Туѓ одговор.'])->assertNotFound();
        $this->as($token)->putJson("/api/v1/me/doctor/reviews/{$pending->id}/reply", ['body' => 'Прерано.'])->assertNotFound();

        $this->assertNull($foreign->fresh()->response_body);
        $this->assertNull($pending->fresh()->response_body);

        // The list carries only this profile's published reviews and the public name.
        $this->as($token)->getJson('/api/v1/me/doctor/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonMissingPath('data.0.user_id');
    }

    public function test_the_review_list_shows_the_public_name_only(): void
    {
        [$doctor, , $token] = $this->linkedDoctor();
        $author = User::factory()->create(['name' => 'Марија Тајна', 'username' => 'marija_k', 'email' => 'marija@example.com']);
        $this->review($doctor, ['user_id' => $author->id]);

        $this->as($token)->getJson('/api/v1/me/doctor/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.author_name', 'marija_k')
            ->assertDontSee('Марија Тајна')
            ->assertDontSee('marija@example.com');
    }

    public function test_a_review_removed_after_publication_is_a_placeholder_without_a_reply(): void
    {
        [$doctor, , $token] = $this->linkedDoctor();
        $staff = User::factory()->create(['user_kind' => UserKind::Staff]);
        $kept = $this->review($doctor, ['published_at' => now()->subDay()]);
        $removed = $this->review($doctor, [
            'body' => 'Текст што беше симнат.',
            'published_at' => now()->subDays(2),
            'response_body' => 'Мој одговор.',
            'response_source' => 'doctor',
            'response_status' => ReviewResponseStatus::Approved,
            'response_at' => now(),
        ]);
        $removed->reject($staff, 'Лични податоци.', category: RemovalCategory::PersonalData);

        $this->as($token)->getJson('/api/v1/me/doctor/reviews')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $kept->id)
            ->assertJsonPath('data.1.id', $removed->id)
            ->assertJsonPath('data.1.removed', true)
            ->assertJsonPath('data.1.removal_category', 'personal_data')
            ->assertJsonMissingPath('data.1.body')
            ->assertJsonMissingPath('data.1.author_name')
            ->assertJsonMissingPath('data.1.reply')
            ->assertDontSee('Текст што беше симнат.')
            ->assertDontSee('Мој одговор.');

        // „Unanswered“ lists only reviews the doctor can still answer.
        $this->as($token)->getJson('/api/v1/me/doctor/reviews?filter=unanswered')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $kept->id);

        $this->as($token)->putJson("/api/v1/me/doctor/reviews/{$removed->id}/reply", ['body' => 'Нов одговор.'])->assertNotFound();
        $this->as($token)->deleteJson("/api/v1/me/doctor/reviews/{$removed->id}/reply")->assertNotFound();
        $this->assertSame('Мој одговор.', $removed->fresh()->response_body);
    }

    public function test_a_suspended_doctor_account_is_refused(): void
    {
        [, $account, $token] = $this->linkedDoctor();
        $account->suspend($this->staff, 'Злоупотреба.');

        $this->as($token)->getJson('/api/v1/me/doctor')->assertUnauthorized();
        $this->as($token)->patchJson('/api/v1/me/doctor', ['phone' => '070 000 000'])->assertUnauthorized();
    }

    public function test_an_unverified_account_cannot_use_the_dashboard(): void
    {
        [, $account, $token] = $this->linkedDoctor();
        $account->forceFill(['email_verified_at' => null])->save();

        $this->as($token)->getJson('/api/v1/me/doctor')->assertForbidden();
    }

    public function test_deleting_the_account_unlinks_the_profile_and_withdraws_what_waits(): void
    {
        [$doctor, $account, $token] = $this->linkedDoctor();
        $review = $this->review($doctor);
        $this->as($token)->postJson('/api/v1/me/doctor/change-requests', ['title' => 'прим. д-р'])->assertCreated();
        $this->as($token)->putJson("/api/v1/me/doctor/reviews/{$review->id}/reply", ['body' => 'Чека одобрување.'])->assertOk();

        app(AnonymiseUser::class)->handle($account);

        $doctor->refresh();
        $this->assertNull($doctor->owner_user_id);
        $this->assertNull($doctor->owner_linked_at);
        $this->assertSame(DoctorChangeRequestStatus::Withdrawn, DoctorChangeRequest::query()->sole()->status);
        $this->assertNull($review->fresh()->response_body);
    }

    public function test_the_data_export_carries_the_doctor_account_parts(): void
    {
        [$doctor, , $token] = $this->linkedDoctor();
        $review = $this->review($doctor);
        $this->as($token)->postJson('/api/v1/me/doctor/change-requests', ['title' => 'прим. д-р'])->assertCreated();
        $this->as($token)->putJson("/api/v1/me/doctor/reviews/{$review->id}/reply", ['body' => 'Благодарам.'])->assertOk();

        $response = $this->as($token)->get('/api/v1/me/export')->assertOk();
        $export = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('ana-petrovska', $export['managed_doctor']['slug']);
        $this->assertSame('pending', $export['doctor_change_requests'][0]['status']);
        $this->assertSame('Благодарам.', $export['doctor_replies'][0]['body']);
        $this->assertSame([], $export['doctor_claim_requests']);
    }

    public function test_the_linked_doctor_cannot_review_their_own_profile(): void
    {
        [, , $token] = $this->linkedDoctor();

        $this->as($token)->postJson('/api/v1/doctors/ana-petrovska/reviews', ['rating' => 5, 'body' => 'Одличен лекар, препорачувам.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['review']);

        $this->assertSame(0, Review::query()->count());
    }

    public function test_the_review_list_is_in_the_public_lists_order(): void
    {
        [$doctor, , $token] = $this->linkedDoctor();
        $newest = $this->review($doctor, ['published_at' => now()->subDay()]);
        $oldest = $this->review($doctor, ['published_at' => now()->subDays(10)]);
        // Taken down before removals kept the publication date: dated by
        // removed_at, not first (PostgreSQL sorts NULL first when descending).
        $backfilled = $this->review($doctor, ['published_at' => now()->subDays(5)]);
        $backfilled->forceFill([
            'status' => 'rejected',
            'published_at' => null,
            'removed_at' => now()->subDays(5),
            'removal_category' => RemovalCategory::Spam,
        ])->save();

        $expected = [$newest->id, $backfilled->id, $oldest->id];

        $this->as($token)->getJson('/api/v1/me/doctor/reviews')
            ->assertOk()
            ->assertJsonPath('data.*.id', $expected);
        $this->getJson('/api/v1/doctors/ana-petrovska/reviews')
            ->assertOk()
            ->assertJsonPath('data.*.id', $expected);
    }

    public function test_a_review_whose_doctor_reply_was_refused_counts_as_unanswered(): void
    {
        [$doctor, $account, $token] = $this->linkedDoctor();
        $refused = $this->review($doctor);
        $refused->replyAsDoctor($account, 'Нешто што не помина.', true);
        $refused->rejectDoctorReply($this->staff, 'Открива пациент.');

        $this->as($token)->getJson('/api/v1/me/doctor')->assertJsonPath('data.stats.unanswered_reviews', 1);
        $this->as($token)->getJson('/api/v1/me/doctor/reviews?filter=unanswered')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $refused->id);
    }

    public function test_an_id_too_long_for_an_integer_is_not_found(): void
    {
        [, , $token] = $this->linkedDoctor();

        $this->as($token)->putJson('/api/v1/me/doctor/reviews/12345678901234567890/reply', ['body' => 'Здраво.'])->assertNotFound();
        $this->as($token)->deleteJson('/api/v1/me/doctor/reviews/12345678901234567890/reply')->assertNotFound();
        $this->as($token)->deleteJson('/api/v1/me/doctor/change-requests/12345678901234567890')->assertNotFound();
    }

    public function test_reply_counts_and_the_unanswered_filter(): void
    {
        [$doctor, , $token] = $this->linkedDoctor();
        $answered = $this->review($doctor);
        $this->review($doctor);
        $answered->replyAsDoctor(User::query()->findOrFail($doctor->owner_user_id), 'Благодарам.', true);

        $this->as($token)->getJson('/api/v1/me/doctor')
            ->assertJsonPath('data.stats.unanswered_reviews', 1)
            ->assertJsonPath('data.stats.pending_replies', 1);

        $this->as($token)->getJson('/api/v1/me/doctor/reviews?filter=unanswered')->assertJsonCount(1, 'data');
        $this->assertSame(ReviewResponseStatus::Pending, $answered->fresh()->response_status);
    }

    private function claim(string $token, string $slug = 'ana-petrovska'): TestResponse
    {
        return $this->as($token)->postJson("/api/v1/doctors/{$slug}/claim-requests", [
            'message' => 'Јас сум д-р Ана Петровска, работам во Клиника Центар.',
            'contact' => '070 123 456',
        ]);
    }

    public function test_a_member_can_ask_to_claim_an_unmanaged_profile_once(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $member = User::factory()->create();
        $token = $member->createToken('web')->plainTextToken;

        $this->claim($token)->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->claim($token)->assertOk();

        $claim = DoctorClaimRequest::query()->sole();
        $this->assertSame($member->id, $claim->user_id);
        $this->assertSame('070 123 456', $claim->contact);
    }

    public function test_a_managed_profile_cannot_be_claimed_and_a_manager_cannot_claim_another(): void
    {
        [, , $ownerToken] = $this->linkedDoctor();
        Doctor::factory()->create(['slug' => 'drug-lekar']);
        $member = User::factory()->create()->createToken('web')->plainTextToken;

        $this->claim($member)->assertConflict()->assertJsonPath('code', 'doctor_account.claim_taken');
        $this->claim($ownerToken)->assertConflict()->assertJsonPath('code', 'doctor_account.claim_already_yours');
        $this->claim($ownerToken, 'drug-lekar')->assertConflict()->assertJsonPath('code', 'doctor_account.claim_already_manager');
        $this->assertSame(0, DoctorClaimRequest::query()->count());
    }

    public function test_claims_need_a_message_and_a_contact(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $token = User::factory()->create()->createToken('web')->plainTextToken;

        $this->as($token)->postJson('/api/v1/doctors/ana-petrovska/claim-requests', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'contact']);
    }

    public function test_deleting_the_account_clears_the_claims_free_text(): void
    {
        Doctor::factory()->create(['slug' => 'ana-petrovska']);
        $member = User::factory()->create();
        $this->claim($member->createToken('web')->plainTextToken)->assertCreated();

        app(AnonymiseUser::class)->handle($member);

        $claim = DoctorClaimRequest::query()->sole();
        $this->assertSame('', $claim->message);
        $this->assertSame('', $claim->contact);
        $this->assertSame('rejected', $claim->status->value);
    }
}
