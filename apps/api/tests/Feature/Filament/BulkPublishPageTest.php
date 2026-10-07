<?php

namespace Tests\Feature\Filament;

use App\Enums\BulkOperationStatus;
use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Enums\UserKind;
use App\Filament\Resources\ImportReviewItems\Pages\ListImportReviewItems;
use App\Filament\Resources\ImportReviewItems\Widgets\BulkPublishProgress;
use App\Jobs\RunBulkPublish;
use App\Models\BulkOperation;
use App\Models\Doctor;
use App\Models\ImportReviewItem;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Import\BulkPublish;
use App\Support\RoleCatalog;
use App\Support\Verification\Engine\VerifiedDraftPublisher;
use App\Support\Verification\VerificationBasis;
use App\Support\Verification\VerificationWriter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * „Објави ги сите верификувани“ on thousands of drafts: the panel starts a
 * background bulk publish and shows its progress instead of publishing
 * inside one request (which ran out of its 30 seconds after ~3,000).
 */
class BulkPublishPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();

        $this->admin = $this->staff(RoleCatalog::ADMINISTRATOR);
        $this->actingAs($this->admin);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['user_kind' => UserKind::Staff, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $user->syncRoles([$role]);

        return $user;
    }

    /**
     * @return list<Doctor>
     */
    private function drafts(int $count): array
    {
        return array_map(function (int $i): Doctor {
            $doctor = Doctor::factory()->unpublished()->create(['full_name' => 'Доктор '.$i]);
            app(VerificationWriter::class)->verify($doctor, VerificationBasis::OfficialRegisters, [['rule' => 'fzom_licence']]);
            ImportReviewItem::raise('fzom', ImportReviewKind::New, 'doctor:'.$doctor->id, $doctor->full_name, [], $doctor);

            return $doctor;
        }, range(1, $count));
    }

    private function chunkOf(int $chunk, int $selectionInlineLimit = BulkPublish::SELECTION_INLINE_LIMIT): void
    {
        $this->app->bind(BulkPublish::class, fn () => new BulkPublish(app(VerifiedDraftPublisher::class), $chunk, $selectionInlineLimit));
    }

    private function published(): int
    {
        return Doctor::query()->where('is_published', true)->count();
    }

    public function test_with_a_sync_queue_the_action_publishes_a_chunk_and_the_progress_poll_the_rest(): void
    {
        $this->chunkOf(2);
        $this->drafts(5);

        Livewire::test(ListImportReviewItems::class)
            ->mountAction('publishVerified')
            ->assertMountedActionModalSee('Publish 5 verified drafts?')
            ->callMountedAction()
            ->assertNotified('Објавувањето започна…')
            ->assertDispatched('bulk-publish-started');

        $this->assertSame(2, $this->published());
        $operation = BulkOperation::query()->sole();
        $this->assertSame(BulkOperation::DRIVER_INLINE, $operation->driver);

        $this->get('/admin/import-review-items')->assertOk()->assertSee('Објавување во тек');

        $widget = Livewire::test(BulkPublishProgress::class)
            ->assertSee('Објавување во тек')
            ->assertSee('2 / 5')
            ->assertSeeHtml('wire:poll.3s.keep-alive="tick"');

        $widget->call('tick')->assertSee('4 / 5')->assertNotDispatched('bulk-publish-finished');
        $this->assertSame(4, $this->published());

        $widget->call('tick')
            ->assertSee('Објавувањето заврши')
            ->assertDontSeeHtml('wire:poll')
            ->assertDispatched('bulk-publish-finished')
            ->assertNotified('Објавувањето заврши');
        $this->assertSame(5, $this->published());
        $this->assertSame(BulkOperationStatus::Completed, $operation->fresh()->status);
        $this->assertSame('Објавувањето заврши', $this->admin->notifications()->sole()->data['title']);
    }

    public function test_a_small_set_is_done_before_the_notification(): void
    {
        $this->drafts(2);

        Livewire::test(ListImportReviewItems::class)
            ->callAction('publishVerified')
            ->assertNotified('Објавувањето заврши');

        $this->assertSame(2, $this->published());
    }

    public function test_with_a_real_queue_the_action_only_dispatches_and_the_page_takes_over_a_stalled_operation(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $this->drafts(3);

        Livewire::test(ListImportReviewItems::class)
            ->callAction('publishVerified')
            ->assertNotified('Објавувањето започна…');

        Queue::assertPushed(RunBulkPublish::class);
        $this->assertSame(0, $this->published(), 'Nothing is published inside the request.');

        // A worker is expected: the page only watches…
        Livewire::test(BulkPublishProgress::class)->call('tick')->assertSee('во позадина');
        $this->assertSame(0, $this->published());

        // …until nothing has moved for a while (no worker running).
        $this->travel(BulkPublish::STALE_SECONDS + 1)->seconds();
        Livewire::test(BulkPublishProgress::class)->assertSee('продолжува додека оваа страница е отворена')->call('tick');
        $this->assertSame(3, $this->published());
    }

    public function test_a_second_bulk_publish_is_refused_while_one_runs(): void
    {
        $this->chunkOf(1);
        $this->drafts(3);
        Livewire::test(ListImportReviewItems::class)->callAction('publishVerified');

        Livewire::test(ListImportReviewItems::class)
            ->callAction('publishVerified')
            ->assertNotified('Веќе тече едно објавување');

        $this->assertSame(1, BulkOperation::query()->count());
        $this->assertSame(1, $this->published());
    }

    public function test_only_staff_who_may_publish_drive_the_operation(): void
    {
        $this->chunkOf(1);
        $this->drafts(3);
        Livewire::test(ListImportReviewItems::class)->callAction('publishVerified');

        $viewer = $this->staff(RoleCatalog::MODERATOR);
        $viewer->givePermissionTo(Permission::findByName('imports.view'));
        $this->actingAs($viewer);

        Livewire::test(BulkPublishProgress::class)->call('tick')->assertSee('1 / 3')->assertDontSee('Прекини');
        $this->assertSame(1, $this->published());
    }

    public function test_a_stopped_operation_can_be_resumed_or_cancelled_from_the_page(): void
    {
        $this->chunkOf(1);
        $this->drafts(3);
        Livewire::test(ListImportReviewItems::class)->callAction('publishVerified');
        $operation = BulkOperation::query()->sole();
        $operation->forceFill(['status' => BulkOperationStatus::Failed, 'error' => 'QueryException: gone'])->save();

        Livewire::test(BulkPublishProgress::class)
            ->assertSee('Објавувањето застана')
            ->assertSee('QueryException: gone')
            ->call('resume')
            ->assertSee('2 / 3');
        $this->assertSame(BulkOperationStatus::Running, $operation->fresh()->status);

        Livewire::test(BulkPublishProgress::class)->call('cancel')->assertDispatched('bulk-publish-finished');
        $this->assertSame(BulkOperationStatus::Cancelled, $operation->fresh()->status);
        $this->assertSame(2, $this->published());
    }

    public function test_a_large_selection_is_published_in_the_background_and_a_small_one_at_once(): void
    {
        $this->chunkOf(2, selectionInlineLimit: 2);
        $doctors = $this->drafts(5);
        $items = ImportReviewItem::query()->orderBy('id')->get();

        Livewire::test(ListImportReviewItems::class)
            ->callTableBulkAction('publish', $items->take(2))
            ->assertNotified('Published 2 profiles');
        $this->assertSame(0, BulkOperation::query()->count());
        $this->assertSame(2, $this->published());

        // Three selected: over the limit.
        Livewire::test(ListImportReviewItems::class)
            ->callTableBulkAction('publish', $items->slice(2))
            ->assertNotified('Објавувањето започна…');
        $operation = BulkOperation::query()->sole();
        $this->assertSame(BulkPublish::TYPE_SELECTED, $operation->type);
        $this->assertSame(3, $operation->total);
        $this->assertSame(4, $this->published());

        Livewire::test(BulkPublishProgress::class)->call('tick');
        $this->assertSame(BulkOperationStatus::Completed, $operation->fresh()->status);
        foreach ($doctors as $doctor) {
            $this->assertTrue($doctor->fresh()->is_published);
        }
    }

    public function test_dismiss_selected_closes_every_selected_open_item(): void
    {
        $this->drafts(3);
        $items = ImportReviewItem::query()->get();

        Livewire::test(ListImportReviewItems::class)->callTableBulkAction('dismiss', $items);

        foreach ($items as $item) {
            $item->refresh();
            $this->assertSame(ImportReviewStatus::Dismissed, $item->status);
            $this->assertSame($this->admin->id, $item->resolved_by_id);
            $this->assertNotNull($item->resolved_at);
        }
        $this->assertSame(0, $this->published());
    }
}
