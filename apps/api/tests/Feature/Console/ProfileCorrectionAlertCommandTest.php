<?php

namespace Tests\Feature\Console;

use App\Enums\ProfileCorrectionField;
use App\Enums\ProfileCorrectionStatus;
use App\Enums\ProfileCorrectionType;
use App\Enums\UserKind;
use App\Mail\NewProfileCorrectionsMail;
use App\Models\Doctor;
use App\Models\ProfileCorrection;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\RoleCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Corrections are due in 15 days and objections in 30 from receipt; this is
 * what tells staff one has arrived without anyone watching the panel.
 */
class ProfileCorrectionAlertCommandTest extends TestCase
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

    private function staff(string $role): User
    {
        $user = User::factory()->create(['user_kind' => UserKind::Staff]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function request(ProfileCorrectionType $type, array $attributes = []): ProfileCorrection
    {
        return ProfileCorrection::query()->create([
            'type' => $type,
            'subject_type' => Doctor::class,
            'subject_id' => Doctor::factory()->create(['full_name' => 'д-р Тајна Личност'])->id,
            'field' => $type === ProfileCorrectionType::Correction ? ProfileCorrectionField::Contact : null,
            'message' => 'Лична порака од барателот.',
            'contact' => 'baratel@example.test',
            'due_at' => now()->addDays($type->dueDays()),
            ...$attributes,
        ]);
    }

    public function test_one_summary_goes_to_staff_who_can_see_the_queue_and_the_shared_inbox(): void
    {
        config(['zdravje.corrections.alert_email' => 'ispravki@example.test']);
        $admin = $this->staff(RoleCatalog::ADMINISTRATOR);
        $moderator = $this->staff(RoleCatalog::MODERATOR);

        $this->request(ProfileCorrectionType::Correction);
        $this->request(ProfileCorrectionType::Correction);
        $this->request(ProfileCorrectionType::Objection);
        $overdue = $this->request(ProfileCorrectionType::Correction, ['due_at' => now()->subDay(), 'staff_alerted_at' => now()->subDays(16)]);
        $closed = $this->request(ProfileCorrectionType::Correction, ['status' => ProfileCorrectionStatus::Resolved, 'resolved_at' => now()]);

        $this->artisan('corrections:alert-staff')->assertSuccessful();

        Mail::assertQueuedCount(2);
        foreach ([$admin->email, 'ispravki@example.test'] as $address) {
            Mail::assertQueued(NewProfileCorrectionsMail::class, fn (NewProfileCorrectionsMail $mail): bool => $mail->hasTo($address));
        }
        Mail::assertNotQueued(NewProfileCorrectionsMail::class, fn (NewProfileCorrectionsMail $mail): bool => $mail->hasTo($moderator->email));

        Mail::assertQueued(NewProfileCorrectionsMail::class, function (NewProfileCorrectionsMail $mail): bool {
            $text = $mail->envelope()->subject.$mail->render();

            return $mail->newRequests === 3
                && $mail->openRequests === 4
                && $mail->overdueRequests === 1
                && str_contains($text, 'Грешка во профилот')
                && str_contains($text, 'Приговор или барање за отстранување')
                && ! str_contains($text, 'Тајна')
                && ! str_contains($text, 'baratel@example.test')
                && ! str_contains($text, 'Лична порака');
        });

        $this->assertSame(0, ProfileCorrection::query()->open()->whereNull('staff_alerted_at')->count());
        $this->assertNull($closed->fresh()->staff_alerted_at);
        $this->assertTrue($overdue->fresh()->staff_alerted_at->lt(now()->subDays(15)));
    }

    public function test_requests_are_announced_once_and_a_quiet_queue_sends_nothing(): void
    {
        $this->staff(RoleCatalog::ADMINISTRATOR);
        $this->request(ProfileCorrectionType::Correction);

        $this->artisan('corrections:alert-staff')->assertSuccessful();
        $this->artisan('corrections:alert-staff')->assertSuccessful();

        Mail::assertQueuedCount(1);
    }

    public function test_it_is_scheduled_every_ten_minutes_with_daily_pruning(): void
    {
        $events = collect(app(Schedule::class)->events());

        $alert = $events->filter(fn (Event $event): bool => str_contains((string) $event->command, 'corrections:alert-staff'))->values();
        $this->assertCount(1, $alert);
        $this->assertSame('*/10 * * * *', $alert[0]->expression);

        $prune = $events->filter(fn (Event $event): bool => str_contains((string) $event->command, 'model:prune')
            && str_contains((string) $event->command, 'ProfileCorrection'))->values();
        $this->assertCount(1, $prune);
    }
}
