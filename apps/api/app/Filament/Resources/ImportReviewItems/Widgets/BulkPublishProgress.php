<?php

namespace App\Filament\Resources\ImportReviewItems\Widgets;

use App\Enums\BulkOperationStatus;
use App\Models\BulkOperation;
use App\Models\User;
use App\Support\Import\BulkPublish;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

/**
 * Progress of the bulk publish above the import review table. It polls
 * while the operation runs; when nothing else drives it (the queue is
 * `sync`, or no worker has picked it up for BulkPublish::STALE_SECONDS) each
 * poll publishes the next chunk within the request's time budget. The poll
 * keeps going in a background tab; closing the page pauses it, and opening
 * Import review again (any staff member who may publish) carries on.
 */
class BulkPublishProgress extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.bulk-publish-progress';

    protected int|string|array $columnSpan = 'full';

    /** The status seen on the last render, to notice the finish once. */
    public ?string $seenStatus = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('imports.view') ?? false;
    }

    public function operation(): ?BulkOperation
    {
        return app(BulkPublish::class)->unfinished()
            ?? BulkOperation::query()->where('finished_at', '>=', now()->subMinutes(15))->latest('id')->first();
    }

    #[On('bulk-publish-started')]
    public function tick(): void
    {
        $operation = $this->operation();

        if ($operation === null) {
            return;
        }

        $bulk = app(BulkPublish::class);

        if ($this->canManage() && $bulk->needsDriving($operation)) {
            $operation = $bulk->drive($operation, BulkPublish::requestBudget());
        }

        if ($this->seenStatus === BulkOperationStatus::Running->value && $operation->status !== BulkOperationStatus::Running) {
            $notification = Notification::make()->title($operation->status === BulkOperationStatus::Completed ? 'Објавувањето заврши' : 'Објавувањето застана')->body(BulkPublish::summary($operation));
            ($operation->status === BulkOperationStatus::Completed && $operation->failed === 0 ? $notification->success() : $notification->warning())->send();
            $this->dispatch('bulk-publish-finished');
        }

        $this->seenStatus = $operation->status->value;
    }

    public function resume(): void
    {
        $operation = app(BulkPublish::class)->unfinished();

        if ($operation !== null && $this->canManage()) {
            app(BulkPublish::class)->resume($operation);
            $this->tick();
        }
    }

    public function cancel(): void
    {
        $operation = app(BulkPublish::class)->unfinished();

        if ($operation !== null && $this->canManage()) {
            app(BulkPublish::class)->cancel($operation);
            $this->seenStatus = BulkOperationStatus::Cancelled->value;
            $this->dispatch('bulk-publish-finished');
        }
    }

    private function canManage(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('imports.manage');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $operation = $this->operation();
        $this->seenStatus ??= $operation?->status->value;

        return [
            'operation' => $operation,
            'label' => $operation !== null ? BulkPublish::label($operation->type) : null,
            'percent' => $operation !== null && $operation->total > 0 ? min(100, (int) floor($operation->processed * 100 / $operation->total)) : 0,
            'stalled' => $operation !== null && $operation->driver === BulkOperation::DRIVER_QUEUE && app(BulkPublish::class)->needsDriving($operation),
            'canManage' => $this->canManage(),
        ];
    }
}
