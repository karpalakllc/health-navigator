<?php

namespace Tests\Feature\Import;

use App\Enums\BulkOperationStatus;
use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Enums\UserKind;
use App\Jobs\RunBulkPublish;
use App\Models\BulkOperation;
use App\Models\Doctor;
use App\Models\ImportReviewItem;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Import\BulkPublish;
use App\Support\Import\BulkPublishAlreadyRunning;
use App\Support\Import\ImportReviewActions;
use App\Support\RoleCatalog;
use App\Support\Verification\Engine\VerifiedDraftPublisher;
use App\Support\Verification\VerificationBasis;
use App\Support\Verification\VerificationWriter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Jobs\MakeSearchable;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The background bulk publish behind „Објави ги сите верификувани“ and
 * `import:publish`: chunked, resumable from its cursor, never more than the
 * confirmed snapshot, one at a time, and the starter told when it is done.
 */
class BulkPublishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SiteSetting::current();
    }

    private function admin(string $email = 'owner@example.test'): User
    {
        $user = User::factory()->create(['email' => $email, 'user_kind' => UserKind::Staff, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $user->syncRoles([RoleCatalog::ADMINISTRATOR]);

        return $user;
    }

    private function draft(string $name, bool $verified = true): Doctor
    {
        $doctor = Doctor::factory()->unpublished()->create(['full_name' => $name]);

        if ($verified) {
            app(VerificationWriter::class)->verify($doctor, VerificationBasis::OfficialRegisters, [['rule' => 'fzom_licence']]);
        }

        ImportReviewItem::raise('fzom', ImportReviewKind::New, 'doctor:'.$doctor->id, $name, [], $doctor);

        return $doctor;
    }

    /**
     * @return list<Doctor>
     */
    private function drafts(int $count): array
    {
        return array_map(fn (int $i): Doctor => $this->draft('Доктор '.$i), range(1, $count));
    }

    private function bulk(int $chunk = 2): BulkPublish
    {
        return new BulkPublish(app(VerifiedDraftPublisher::class), $chunk);
    }

    private function maxPendingId(): int
    {
        return (int) app(VerifiedDraftPublisher::class)->pending()->max('id');
    }

    public function test_steps_publish_a_chunk_each_and_continue_from_the_cursor(): void
    {
        $doctors = $this->drafts(5);
        $admin = $this->admin();
        $bulk = $this->bulk(2);

        $operation = $bulk->start(BulkPublish::TYPE_VERIFIED, $admin, $this->maxPendingId(), null, BulkOperation::DRIVER_INLINE);
        $this->assertSame(5, $operation->total);

        $operation = $bulk->step($operation);
        $this->assertSame(2, $operation->published);
        $this->assertSame(BulkOperationStatus::Running, $operation->status);
        $this->assertSame(2, Doctor::query()->where('is_published', true)->count());

        $operation = $bulk->step($bulk->step($operation));
        $this->assertSame(BulkOperationStatus::Completed, $operation->status);
        $this->assertSame(5, $operation->published);
        $this->assertSame(5, $operation->processed);
        $this->assertNotNull($operation->finished_at);

        foreach ($doctors as $doctor) {
            $this->assertTrue($doctor->fresh()->is_published);
        }
        // Resolved by the staff member who confirmed, also from a later step.
        $this->assertSame(5, ImportReviewItem::query()->where('resolved_by_id', $admin->id)->where('resolution', 'published')->count());
    }

    public function test_a_resumed_operation_continues_where_it_stopped_and_publishes_nothing_twice(): void
    {
        $doctors = $this->drafts(4);
        $bulk = $this->bulk(2);
        $operation = $bulk->step($bulk->start(BulkPublish::TYPE_VERIFIED, $this->admin(), $this->maxPendingId(), null, BulkOperation::DRIVER_INLINE));
        $firstPublishedAt = $doctors[0]->fresh()->published_at;

        // Died after committing the chunk but before saving the cursor, then
        // stopped on an error; resumed.
        $operation->forceFill(['cursor' => 0, 'status' => BulkOperationStatus::Failed])->save();
        $operation = $bulk->resume($operation, BulkOperation::DRIVER_INLINE);
        while ($operation->isRunning()) {
            $operation = $bulk->step($operation);
        }

        $this->assertSame(BulkOperationStatus::Completed, $operation->status);
        $this->assertSame(4, $operation->published);
        $this->assertEquals($firstPublishedAt, $doctors[0]->fresh()->published_at);
        $this->assertSame(0, ImportReviewItem::query()->open()->count());
    }

    public function test_it_publishes_no_more_than_the_snapshot_and_nothing_that_left_the_set(): void
    {
        $first = $this->draft('Ана Прва');
        $blocked = $this->draft('Бранко Блокиран');
        $snapshot = $this->maxPendingId();
        $late = $this->draft('Вера Задоцнета');

        $bulk = $this->bulk(10);
        $operation = $bulk->start(BulkPublish::TYPE_VERIFIED, $this->admin(), $snapshot, null, BulkOperation::DRIVER_INLINE);
        $this->assertSame(2, $operation->total);

        // A conflict raised after the confirmation takes it out of the set.
        ImportReviewItem::raise('fzom', ImportReviewKind::Conflict, 'conflict:'.$blocked->id, 'Conflict', ['field' => 'city'], $blocked);
        $operation = $bulk->step($operation);

        $this->assertSame(BulkOperationStatus::Completed, $operation->status);
        $this->assertTrue($first->fresh()->is_published);
        $this->assertFalse($blocked->fresh()->is_published);
        $this->assertFalse($late->fresh()->is_published);
    }

    public function test_the_search_index_gets_one_update_per_chunk_after_the_commits(): void
    {
        $this->drafts(3);
        $items = ImportReviewItem::query()->pluck('id')->all();
        config(['scout.queue' => true]);
        Queue::fake();

        $result = app(VerifiedDraftPublisher::class)->publishChunk($items, $this->admin());

        $this->assertSame(3, $result['published']);
        Queue::assertPushed(MakeSearchable::class, 1);
        Queue::assertPushed(MakeSearchable::class, fn (MakeSearchable $job): bool => $job->models->count() === 3 && $job->models->every(fn (Doctor $doctor): bool => $doctor->is_published));
    }

    public function test_only_one_bulk_publish_runs_at_a_time(): void
    {
        $this->drafts(3);
        $bulk = $this->bulk(1);
        $admin = $this->admin();
        $operation = $bulk->start(BulkPublish::TYPE_VERIFIED, $admin, $this->maxPendingId(), null, BulkOperation::DRIVER_INLINE);

        try {
            $bulk->start(BulkPublish::TYPE_FZOM_UNVERIFIED, $admin, null, null, BulkOperation::DRIVER_INLINE);
            $this->fail('A second bulk publish started.');
        } catch (BulkPublishAlreadyRunning $exception) {
            $this->assertTrue($exception->operation->is($operation));
        }

        // A stopped one blocks too, until it is resumed or cancelled.
        $operation->forceFill(['status' => BulkOperationStatus::Failed])->save();
        $this->expectException(BulkPublishAlreadyRunning::class);
        $bulk->start(BulkPublish::TYPE_VERIFIED, $admin, null, null, BulkOperation::DRIVER_INLINE);
    }

    public function test_a_cancelled_operation_frees_the_slot_and_keeps_what_was_published(): void
    {
        $this->drafts(3);
        $bulk = $this->bulk(1);
        $admin = $this->admin();
        $operation = $bulk->step($bulk->start(BulkPublish::TYPE_VERIFIED, $admin, $this->maxPendingId(), null, BulkOperation::DRIVER_INLINE));

        $bulk->cancel($operation);
        $this->assertSame(BulkOperationStatus::Cancelled, $operation->fresh()->status);
        $this->assertSame(1, $bulk->step($operation)->published, 'A cancelled operation publishes nothing more.');
        $this->assertSame(1, Doctor::query()->where('is_published', true)->count());

        $this->assertSame(2, $bulk->start(BulkPublish::TYPE_VERIFIED, $admin, $this->maxPendingId(), null, BulkOperation::DRIVER_INLINE)->total);
    }

    public function test_a_cancel_during_a_step_stops_it_and_stays_cancelled(): void
    {
        $this->drafts(3);
        $operation = $this->bulk()->start(BulkPublish::TYPE_VERIFIED, $this->admin(), $this->maxPendingId(), null, BulkOperation::DRIVER_INLINE);

        // Staff cancel while the step publishes its first item.
        $cancelled = false;
        Doctor::saved(function () use ($operation, &$cancelled): void {
            if (! $cancelled) {
                $cancelled = true;
                BulkOperation::query()->whereKey($operation->id)->update(['status' => BulkOperationStatus::Cancelled->value]);
            }
        });

        // One item per sub-batch: the cancel is seen before the next.
        $bulk = new BulkPublish(app(VerifiedDraftPublisher::class), 10, BulkPublish::SELECTION_INLINE_LIMIT, 1);
        $this->assertSame(BulkOperationStatus::Cancelled, $bulk->step($operation)->status);
        $this->assertSame(1, Doctor::query()->where('is_published', true)->count());
    }

    public function test_a_step_another_driver_holds_does_nothing(): void
    {
        $this->drafts(2);
        $bulk = $this->bulk(5);
        $operation = $bulk->start(BulkPublish::TYPE_VERIFIED, $this->admin(), $this->maxPendingId(), null, BulkOperation::DRIVER_INLINE);

        $lock = Cache::lock('import:bulk-publish:'.$operation->id, 60);
        $this->assertTrue($lock->get());

        $this->assertSame(0, $bulk->step($operation)->processed);
        $this->assertSame(0, Doctor::query()->where('is_published', true)->count());

        $lock->release();
        $this->assertSame(BulkOperationStatus::Completed, $bulk->step($operation)->status);
    }

    public function test_the_starter_is_notified_with_the_counts_and_the_failures(): void
    {
        $this->draft('Ана Добра');
        $broken = $this->draft('Пад Пример');
        $brokenItem = ImportReviewItem::query()->where('subject_id', $broken->id)->firstOrFail();
        Doctor::saving(function (Doctor $doctor): void {
            if ($doctor->full_name === 'Пад Пример' && $doctor->is_published) {
                throw new RuntimeException('boom');
            }
        });
        $admin = $this->admin();
        $bulk = $this->bulk(10);

        $operation = $bulk->step($bulk->start(BulkPublish::TYPE_VERIFIED, $admin, $this->maxPendingId(), null, BulkOperation::DRIVER_INLINE));

        $this->assertSame(BulkOperationStatus::Completed, $operation->status);
        $this->assertSame(1, $operation->published);
        $this->assertSame(1, $operation->failed);
        $this->assertSame($brokenItem->id, $operation->failures[0]['item']);
        $this->assertFalse($broken->fresh()->is_published);
        $this->assertSame(ImportReviewStatus::Open, $brokenItem->fresh()->status, 'Rolled back with its transaction: still to decide.');

        $notification = $admin->notifications()->sole();
        $this->assertSame('Објавувањето заврши', $notification->data['title']);
        $this->assertStringContainsString('објавени 1 од 2', $notification->data['body']);
        $this->assertStringContainsString('Неуспешни 1', $notification->data['body']);
        $this->assertStringContainsString('#'.$brokenItem->id, $notification->data['body']);
    }

    public function test_with_a_real_queue_the_job_publishes_everything_and_hands_over_when_another_driver_holds_it(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $this->drafts(3);
        $admin = $this->admin();

        $operation = app(BulkPublish::class)->start(BulkPublish::TYPE_VERIFIED, $admin, $this->maxPendingId());
        $this->assertSame(BulkOperation::DRIVER_QUEUE, $operation->driver);
        Queue::assertPushed(RunBulkPublish::class, fn (RunBulkPublish $job): bool => $job->operationId === $operation->id);
        $this->assertSame(0, Doctor::query()->where('is_published', true)->count(), 'Nothing in the request.');

        // The review page holds the step: the job waits, as a fresh job.
        $lock = Cache::lock('import:bulk-publish:'.$operation->id, 60);
        $lock->get();
        Queue::fake();
        (new RunBulkPublish($operation->id))->handle(app(BulkPublish::class));
        Queue::assertPushed(RunBulkPublish::class, fn (RunBulkPublish $job): bool => $job->delay === 15);
        $lock->release();

        (new RunBulkPublish($operation->id))->handle(app(BulkPublish::class));

        $this->assertSame(BulkOperationStatus::Completed, $operation->fresh()->status);
        $this->assertSame(3, Doctor::query()->where('is_published', true)->count());
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_a_failed_job_stops_the_operation_resumable_and_tells_the_starter(): void
    {
        Queue::fake();
        $this->drafts(1);
        $admin = $this->admin();
        $operation = $this->bulk()->start(BulkPublish::TYPE_VERIFIED, $admin, $this->maxPendingId(), null, BulkOperation::DRIVER_QUEUE);

        (new RunBulkPublish($operation->id))->failed(new RuntimeException('database went away'));

        $operation->refresh();
        $this->assertSame(BulkOperationStatus::Failed, $operation->status);
        $this->assertStringContainsString('database went away', (string) $operation->error);
        $this->assertSame('Објавувањето застана', $admin->notifications()->sole()->data['title']);
        $this->assertSame($operation->id, $this->bulk()->unfinished()?->id);
    }

    public function test_a_queued_operation_needs_driving_only_once_it_stalled(): void
    {
        Queue::fake();
        $this->drafts(1);
        $operation = $this->bulk()->start(BulkPublish::TYPE_VERIFIED, $this->admin(), $this->maxPendingId(), null, BulkOperation::DRIVER_QUEUE);

        $this->assertFalse($this->bulk()->needsDriving($operation));
        $this->travel(BulkPublish::STALE_SECONDS + 1)->seconds();
        $this->assertTrue($this->bulk()->needsDriving($operation));

        $operation->forceFill(['driver' => BulkOperation::DRIVER_INLINE, 'heartbeat_at' => now()])->save();
        $this->assertTrue($this->bulk()->needsDriving($operation));
    }

    public function test_the_command_publishes_a_set_in_chunks_and_a_rerun_continues_the_stopped_operation(): void
    {
        $this->app->bind(BulkPublish::class, fn () => new BulkPublish(app(VerifiedDraftPublisher::class), 2));
        $this->drafts(5);
        $admin = $this->admin();

        $this->artisan('import:publish', ['set' => 'verified', '--dry-run' => true])
            ->expectsOutputToContain('Would publish 5 drafts')
            ->assertSuccessful();
        $this->assertSame(0, Doctor::query()->where('is_published', true)->count());
        $this->assertSame(0, BulkOperation::query()->count());

        // A run stopped after the first chunk…
        $bulk = app(BulkPublish::class);
        $stopped = $bulk->step($bulk->start(BulkPublish::TYPE_VERIFIED, $admin, $this->maxPendingId(), null, BulkOperation::DRIVER_CLI));
        $stopped->forceFill(['status' => BulkOperationStatus::Failed])->save();

        // …is continued, with its snapshot, by running the command again.
        $this->draft('Задоцнет Доктор');
        $this->artisan('import:publish', ['set' => 'verified', '--by' => 'owner@example.test'])
            ->expectsOutputToContain('Continuing bulk publish #'.$stopped->id)
            ->expectsOutputToContain('објавени 5 од 5')
            ->assertSuccessful();

        $this->assertSame(1, BulkOperation::query()->count());
        $this->assertSame(BulkOperationStatus::Completed, $stopped->fresh()->status);
        $this->assertSame(5, Doctor::query()->where('is_published', true)->count());
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_the_command_refuses_an_unknown_set_or_staff_member(): void
    {
        $this->artisan('import:publish', ['set' => 'everything'])->assertExitCode(2);
        $this->artisan('import:publish', ['set' => 'verified', '--by' => 'nobody@example.test'])
            ->expectsOutputToContain('No staff member')
            ->assertFailed();
    }

    public function test_the_command_publishes_the_fzom_unverified_set_as_the_given_staff_member(): void
    {
        $doctor = $this->draft('Ана Фзомска', false);
        app(VerificationWriter::class)->unverify($doctor, 'fzom_no_licence');
        $verified = $this->draft('Бранко Верификуван');
        $admin = $this->admin();

        $this->artisan('import:publish', ['set' => 'fzom-unverified', '--by' => $admin->email])->assertSuccessful();

        $this->assertTrue($doctor->fresh()->is_published);
        $this->assertFalse($doctor->fresh()->isVerified());
        $this->assertFalse($verified->fresh()->is_published);
        $this->assertSame($admin->id, ImportReviewItem::query()->where('subject_id', $doctor->id)->value('resolved_by_id'));
    }

    /**
     * „Спои ги“ deletes a website draft while a background bulk publish may
     * be on the same item: the publish locks the profile first and finds it
     * gone, instead of closing the item as published for a deleted draft.
     */
    public function test_a_draft_merged_away_after_it_was_loaded_is_not_counted_as_published(): void
    {
        $doctor = $this->draft('Ана Спојувана', verified: false);
        $item = ImportReviewItem::query()->where('subject_id', $doctor->id)->firstOrFail();
        $merged = false;

        // The merge lands between loading the profile and publishing it.
        Doctor::retrieved(function (Doctor $loaded) use ($doctor, &$merged): void {
            if (! $merged && $loaded->is($doctor)) {
                $merged = true;
                DB::table('doctors')->where('id', $doctor->id)->delete();
            }
        });

        $published = app(ImportReviewActions::class)->publish($item, $this->admin());

        $this->assertTrue($merged);
        $this->assertFalse($published);
        $this->assertSame(ImportReviewStatus::Open, $item->refresh()->status);
    }
}
