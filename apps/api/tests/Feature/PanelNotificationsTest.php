<?php

namespace Tests\Feature;

use App\Actions\AnonymiseUser;
use App\Models\MemberNotification;
use App\Models\PanelNotification;
use App\Models\User;
use App\Support\AccountExport;
use Filament\Notifications\Notification;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two notification stores, one policy: the members' „Известувања“
 * (member_notifications, W8-B) and Laravel's `notifications` table behind the
 * admin panel's bell (Filament database notifications, e.g. a finished bulk
 * publish). Both are kept MemberNotification::RETENTION_DAYS, are in the
 * account export and go with the account.
 */
class PanelNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, string $title): void
    {
        $user->notifyNow(Notification::make()->title($title)->body('Објавени 3 од 3.')->toDatabase());
    }

    public function test_the_panel_bell_writes_json_data_with_the_filament_format(): void
    {
        $staff = User::factory()->create();
        $this->notify($staff, 'Објавувањето заврши');

        $row = DB::table('notifications')->first();
        $this->assertNotNull($row);
        $this->assertSame('filament', json_decode((string) $row->data, true)['format']);
        // The panel's own query: data->>'format' on PostgreSQL, json on SQLite.
        $this->assertSame(1, $staff->notifications()->where('data->format', 'filament')->count());
    }

    public function test_old_panel_notifications_are_pruned_with_the_member_ones(): void
    {
        $staff = User::factory()->create();
        $this->notify($staff, 'Старо');
        DB::table('notifications')->update(['created_at' => now()->subDays(MemberNotification::RETENTION_DAYS + 1)]);
        $this->notify($staff, 'Ново');

        $this->artisan('model:prune', ['--model' => [MemberNotification::class, PanelNotification::class]])->assertSuccessful();

        $this->assertSame(['Ново'], DB::table('notifications')->pluck('data')->map(fn (string $data): string => json_decode($data, true)['title'])->all());
    }

    public function test_the_daily_prune_covers_both_stores(): void
    {
        $command = collect(app(Schedule::class)->events())
            ->map(fn (Event $event): string => (string) $event->command)
            ->first(fn (string $command): bool => str_contains($command, 'model:prune') && str_contains($command, 'MemberNotification'));

        $this->assertNotNull($command);
        $this->assertStringContainsString('PanelNotification', $command);
    }

    public function test_panel_notifications_are_in_the_export_and_go_with_the_account(): void
    {
        $staff = User::factory()->create();
        $this->notify($staff, 'Објавувањето заврши');
        $this->notify(User::factory()->create(), 'Туѓо');

        ob_start();
        (new AccountExport($staff))->write();
        $export = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $export['panel_notifications']);
        $this->assertSame('Објавувањето заврши', $export['panel_notifications'][0]['title']);
        $this->assertSame('Објавени 3 од 3.', $export['panel_notifications'][0]['body']);

        app(AnonymiseUser::class)->handle($staff);

        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame(0, $staff->notifications()->count());
    }
}
