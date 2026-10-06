<?php

namespace Tests\Feature;

use App\Actions\DoctorAccount\AssignDoctorOwner;
use App\Actions\DoctorAccount\DecideDoctorChangeRequest;
use App\Enums\UserKind;
use App\Filament\Resources\Clients\Pages\EditClientUser;
use App\Models\Activity;
use App\Models\ContentReport;
use App\Models\Doctor;
use App\Models\DoctorChangeRequest;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The audit log (spatie/laravel-activitylog): who changed what on doctor
 * profiles, change requests, review replies, reports and accounts — and what
 * it must never hold.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
        Mail::fake();
    }

    private function latestFor(object $subject): ?Activity
    {
        return Activity::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->latest('id')
            ->first();
    }

    public function test_toggling_featured_or_sponsored_is_logged_with_who_did_it(): void
    {
        $admin = User::factory()->admin()->create();
        $doctor = Doctor::factory()->create(['is_featured' => false, 'is_sponsored' => false]);
        $this->actingAs($admin);

        $doctor->update(['is_featured' => true]);

        $entry = $this->latestFor($doctor);
        $this->assertNotNull($entry);
        $this->assertSame('doctor_profile', $entry->log_name);
        $this->assertSame('updated', $entry->event);
        $this->assertSame($admin->id, (int) $entry->causer_id);
        $this->assertSame(['is_featured' => true], $entry->attribute_changes['attributes']);
        $this->assertSame(['is_featured' => false], $entry->attribute_changes['old']);

        $doctor->update(['is_featured' => false]);
        $doctor->update(['is_sponsored' => true]);
        $this->assertSame(['is_sponsored' => true], $this->latestFor($doctor)->attribute_changes['attributes']);
    }

    public function test_assigning_the_doctor_account_is_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $doctor = Doctor::factory()->create();
        $member = User::factory()->create();
        $this->actingAs($admin);

        app(AssignDoctorOwner::class)->handle($doctor, $member, $admin);

        $entry = $this->latestFor($doctor);
        $this->assertSame($member->id, $entry->attribute_changes['attributes']['owner_user_id']);
        $this->assertSame($admin->id, (int) $entry->causer_id);
    }

    public function test_change_request_decisions_and_the_profile_change_are_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $doctor = Doctor::factory()->create(['full_name' => 'д-р Стара']);
        $request = DoctorChangeRequest::query()->create([
            'doctor_id' => $doctor->id,
            'user_id' => User::factory()->create()->id,
            'changes' => ['full_name' => ['old' => 'д-р Стара', 'new' => 'д-р Нова']],
        ]);
        $this->actingAs($admin);

        app(DecideDoctorChangeRequest::class)->approve($request, $admin);

        $decision = $this->latestFor($request);
        $this->assertSame('doctor_accounts', $decision->log_name);
        $this->assertSame('approved', $decision->attribute_changes['attributes']['status']);
        $this->assertSame($admin->id, (int) $decision->causer_id);

        $profile = $this->latestFor($doctor);
        $this->assertSame('д-р Нова', $profile->attribute_changes['attributes']['full_name']);
    }

    public function test_review_replies_are_logged_without_the_review_text_or_author(): void
    {
        $moderator = User::factory()->moderator()->create();
        $review = Review::factory()->approved()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => Doctor::factory(),
            'body' => 'Лична здравствена приказна.',
        ]);
        $this->actingAs($moderator);

        $review->respond($moderator, 'Благодариме.');

        $entry = $this->latestFor($review);
        $this->assertSame('reviews', $entry->log_name);
        $this->assertSame('Благодариме.', $entry->attribute_changes['attributes']['response_body']);
        $this->assertSame('staff', $entry->attribute_changes['attributes']['response_source']);

        $raw = json_encode(Activity::query()->get()->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Лична здравствена приказна.', (string) $raw);
        $this->assertStringNotContainsString('"user_id"', (string) $raw);
    }

    public function test_resolving_a_report_is_logged_against_the_content(): void
    {
        $moderator = User::factory()->moderator()->create();
        $review = Review::factory()->approved()->create([
            'reviewable_type' => Doctor::class,
            'reviewable_id' => Doctor::factory(),
        ]);
        $report = ContentReport::factory()->about($review)->create(['note' => 'Тајна белешка од пријавувачот.']);
        $this->actingAs($moderator);

        $report->keepContent($moderator);

        $entry = Activity::query()->where('log_name', 'reports')->sole();
        $this->assertSame('kept', $entry->event);
        $this->assertSame(Review::class, $entry->subject_type);
        $this->assertSame($review->id, (int) $entry->subject_id);
        $this->assertSame($moderator->id, (int) $entry->causer_id);
        $this->assertSame(1, $entry->getProperty('reports_closed'));
        $this->assertStringNotContainsString('Тајна белешка', (string) json_encode($entry->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_suspending_an_account_is_logged_without_the_reason(): void
    {
        $admin = User::factory()->create([
            'user_kind' => UserKind::Staff,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
        $admin->syncRoles(['Administrator']);
        $member = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test(EditClientUser::class, ['record' => $member->getRouteKey()])
            ->callAction('suspend', ['reason' => 'Повеќекратен спам.']);

        $entry = $this->latestFor($member);
        $this->assertSame('accounts', $entry->log_name);
        $this->assertSame('suspended', $entry->event);
        $this->assertSame($admin->id, (int) $entry->causer_id);
        $this->assertStringNotContainsString('спам', (string) json_encode($entry->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_secrets_are_never_logged_whatever_model_logs_them(): void
    {
        $excluded = config('activitylog.default_except_attributes');

        foreach (['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'] as $attribute) {
            $this->assertContains($attribute, $excluded);
        }

        $this->assertSame(['ip', 'user_agent'], array_values(array_diff(['ip', 'user_agent'], Schema::getColumnListing('activity_log'))));
    }

    public function test_entries_older_than_a_year_are_pruned_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'activitylog:clean'))
            ->values();
        $this->assertCount(1, $events);
        $this->assertSame('45 4 * * *', $events[0]->expression);
        $this->assertSame(365, config('activitylog.clean_after_days'));

        $doctor = Doctor::factory()->create();
        $old = Activity::query()->create(['description' => 'old', 'log_name' => 'doctor_profile']);
        $old->forceFill(['created_at' => now()->subDays(366)])->save();
        $recent = $this->latestFor($doctor);

        $this->artisan('activitylog:clean', ['--force' => true])->assertSuccessful();

        $this->assertNull(Activity::query()->find($old->id));
        $this->assertNotNull(Activity::query()->find($recent->id));
    }
}
