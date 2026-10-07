<?php

namespace Tests\Feature\Filament;

use App\Enums\ProfileCorrectionField;
use App\Enums\ProfileCorrectionStatus;
use App\Enums\ProfileCorrectionType;
use App\Enums\ProfileReportReason;
use App\Enums\UserKind;
use App\Filament\Resources\ProfileCorrections\Pages\ListProfileCorrections;
use App\Filament\Resources\ProfileCorrections\ProfileCorrectionResource;
use App\Mail\NewProfileCorrectionsMail;
use App\Models\Doctor;
use App\Models\ProfileCorrection;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Profile reports in the corrections queue (W7-C): a profile reported by
 * several independent people floats to the top with a red count, one
 * decision closes every open report on it (and erases the guest hashes),
 * nothing is hidden automatically, and the batched staff alert says how
 * many profiles are over the threshold.
 */
class ProfileReportQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['user_kind' => UserKind::Staff]);
        $user->syncRoles([RoleCatalog::ADMINISTRATOR]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function report(Doctor $doctor, array $attributes = []): ProfileCorrection
    {
        return ProfileCorrection::query()->create([
            'type' => ProfileCorrectionType::Report,
            'subject_type' => Doctor::class,
            'subject_id' => $doctor->id,
            'report_reason' => ProfileReportReason::FakeProfile,
            'reporter_hash' => bin2hex(random_bytes(32)),
            'due_at' => now()->addDays(7),
            ...$attributes,
        ]);
    }

    public function test_independent_reporters_are_counted_once_each(): void
    {
        $doctor = Doctor::factory()->create();
        $member = User::factory()->create();
        $hash = str_repeat('a', 64);

        $first = $this->report($doctor, ['reporter_hash' => $hash]);
        // The same guest address on another day: the same per-profile hash.
        $this->report($doctor, ['reporter_hash' => $hash]);
        $this->report($doctor, ['reporter_hash' => null, 'user_id' => $member->id]);
        $this->report($doctor);
        // Closed reports and other profiles do not count.
        $this->report($doctor, ['status' => ProfileCorrectionStatus::Declined, 'resolved_at' => now()]);
        $this->report(Doctor::factory()->create());

        $this->assertSame(3, $first->openReportCount());
        $this->assertSame(3, (int) ProfileCorrection::query()->withOpenReportCount()->whereKey($first->id)->value('open_report_count'));
        $this->assertTrue($first->isPriority());
    }

    public function test_profiles_with_three_independent_reports_are_listed_first_with_a_count(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // Due soonest, but not a report. (Ten rows: one table page.)
        $correction = ProfileCorrection::query()->create([
            'type' => ProfileCorrectionType::Correction,
            'subject_type' => Doctor::class,
            'subject_id' => Doctor::factory()->create()->id,
            'field' => ProfileCorrectionField::OfficeHours,
            'message' => 'Работното време е застарено.',
            'due_at' => now()->addDay(),
        ]);

        $popular = Doctor::factory()->create();
        $popularReports = collect(range(1, 3))->map(fn (int $i) => $this->report($popular, ['due_at' => now()->addDays(6 + $i)]));

        $two = Doctor::factory()->create();
        $twoReports = collect(range(1, 2))->map(fn (int $i) => $this->report($two, ['due_at' => now()->addDays(2 + $i)]));

        $mostPopular = Doctor::factory()->create();
        $mostReports = collect(range(1, 4))->map(fn (int $i) => $this->report($mostPopular, ['due_at' => now()->addDays(20 + $i)]));

        $expected = [...$mostReports->all(), ...$popularReports->all(), $correction, ...$twoReports->all()];

        Livewire::test(ListProfileCorrections::class)
            ->assertCanSeeTableRecords($expected, inOrder: true)
            ->assertTableColumnStateSet('open_report_count', 4, $mostReports->first())
            ->assertTableColumnStateSet('open_report_count', 2, $twoReports->first())
            ->assertTableColumnStateNotSet('open_report_count', 1, $correction)
            ->filterTable('priority')
            ->assertCanSeeTableRecords([...$mostReports->all(), ...$popularReports->all()])
            ->assertCanNotSeeTableRecords([$correction, ...$twoReports->all()]);

        $this->assertSame('danger', ProfileCorrectionResource::getNavigationBadgeColor());
    }

    public function test_one_decision_closes_every_open_report_on_the_profile_and_erases_the_hashes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $doctor = Doctor::factory()->create(['is_published' => true]);
        $reports = collect(range(1, 3))->map(fn () => $this->report($doctor));
        $elsewhere = $this->report(Doctor::factory()->create());

        Livewire::test(ListProfileCorrections::class)
            ->callTableAction('decline', $reports->first(), ['resolution_note' => 'Профилот е проверен и е точен.']);

        foreach ($reports as $report) {
            $report->refresh();
            $this->assertSame(ProfileCorrectionStatus::Declined, $report->status);
            $this->assertSame($admin->id, $report->resolved_by_id);
            $this->assertNull($report->reporter_hash);
        }

        $this->assertSame(ProfileCorrectionStatus::Open, $elsewhere->refresh()->status);
        $this->assertNotNull($elsewhere->reporter_hash);
        // Never hidden by the queue itself.
        $this->assertTrue($doctor->refresh()->is_published);
    }

    public function test_the_staff_alert_counts_reports_and_profiles_over_the_threshold(): void
    {
        Mail::fake();
        $this->admin();
        $popular = Doctor::factory()->create();
        collect(range(1, 3))->each(fn () => $this->report($popular));
        $this->report(Doctor::factory()->create());

        $this->artisan('corrections:alert-staff')->assertSuccessful();

        Mail::assertQueued(NewProfileCorrectionsMail::class, function (NewProfileCorrectionsMail $mail): bool {
            $html = $mail->render();

            return $mail->newRequests === 4
                && $mail->priorityProfiles === 1
                && $mail->types === [['label' => 'Пријава на профил', 'count' => 4, 'days' => 7]]
                && str_contains($html, 'Профили со повеќе пријави од различни лица');
        });
        $this->assertSame(0, ProfileCorrection::query()->whereNull('staff_alerted_at')->count());
    }

    public function test_closed_reports_are_pruned_sooner_than_corrections(): void
    {
        $doctor = Doctor::factory()->create();
        $closedReport = $this->report($doctor, ['status' => ProfileCorrectionStatus::Resolved, 'resolved_at' => now()->subDays(91), 'reporter_hash' => null]);
        $recentReport = $this->report($doctor, ['status' => ProfileCorrectionStatus::Resolved, 'resolved_at' => now()->subDays(80), 'reporter_hash' => null]);
        $openReport = $this->report($doctor, ['due_at' => now()->subDays(200)]);
        $correction = ProfileCorrection::query()->create([
            'type' => ProfileCorrectionType::Correction,
            'subject_type' => Doctor::class,
            'subject_id' => $doctor->id,
            'field' => ProfileCorrectionField::Other,
            'message' => 'Порака за проверка.',
            'status' => ProfileCorrectionStatus::Resolved,
            'resolved_at' => now()->subDays(91),
            'due_at' => now()->subDays(120),
        ]);

        $this->artisan('model:prune', ['--model' => [ProfileCorrection::class]])->assertSuccessful();

        $this->assertModelMissing($closedReport);
        $this->assertModelExists($recentReport);
        $this->assertModelExists($openReport);
        $this->assertModelExists($correction);
    }
}
