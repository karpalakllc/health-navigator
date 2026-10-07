<?php

namespace App\Filament\Resources\ImportReviewItems\Pages;

use App\Enums\BulkOperationStatus;
use App\Enums\ImportReviewKind;
use App\Filament\Resources\ImportReviewItems\ImportReviewItemResource;
use App\Filament\Resources\ImportReviewItems\Widgets\BulkPublishProgress;
use App\Models\BulkOperation;
use App\Models\ImportReviewItem;
use App\Models\User;
use App\Support\Import\BulkPublish;
use App\Support\Import\BulkPublishAlreadyRunning;
use App\Support\Verification\Engine\VerifiedDraftPublisher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

class ListImportReviewItems extends ListRecords
{
    protected static string $resource = ImportReviewItemResource::class;

    /**
     * The highest item id in the set when the bulk modal opened: a click
     * publishes at most what the owner saw counted (items raised while the
     * modal was open wait for the next look).
     */
    public ?int $bulkCeiling = null;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $open = ImportReviewItem::query()->open()->selectRaw('kind, count(*) as aggregate')->groupBy('kind')->pluck('aggregate', 'kind');

        $tabs = ['all' => Tab::make('All')];

        foreach (ImportReviewKind::cases() as $kind) {
            $tabs[$kind->value] = Tab::make($kind->label())
                ->badge(fn (): ?string => ($open[$kind->value] ?? 0) > 0 ? (string) $open[$kind->value] : null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kind', $kind->value));
        }

        return $tabs;
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('publishVerified')
                ->label('Објави ги сите верификувани')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => (auth()->user()?->can('imports.manage') ?? false) && app(VerifiedDraftPublisher::class)->count() > 0)
                ->requiresConfirmation()
                ->mountUsing(function (): void {
                    $this->bulkCeiling = (int) app(VerifiedDraftPublisher::class)->pending()->max('id');
                })
                ->modalHeading(fn (): string => sprintf('Publish %d verified drafts?', app(VerifiedDraftPublisher::class)->count()))
                ->modalDescription(fn (): Htmlable => $this->sampleDescription())
                ->modalSubmitActionLabel('Publish all')
                ->action(fn () => $this->startBulkPublish(BulkPublish::TYPE_VERIFIED, $this->bulkCeiling)),
            // Owner's decision: ФЗОМ lists them today, the Комора list has no
            // licence of their name, nothing else is open on them → public,
            // but „Неверификуван“ (verified later automatically if a licence
            // appears).
            Action::make('publishFzomUnverified')
                ->label('Објави ги и неверификуваните од ФЗОМ')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->visible(fn (): bool => (auth()->user()?->can('imports.manage') ?? false) && app(VerifiedDraftPublisher::class)->countFzomUnverified() > 0)
                ->requiresConfirmation()
                ->mountUsing(function (): void {
                    $this->bulkCeiling = (int) app(VerifiedDraftPublisher::class)->pendingFzomUnverified()->max('id');
                })
                ->modalHeading(fn (): string => sprintf('Publish %d unverified ФЗОМ drafts?', app(VerifiedDraftPublisher::class)->countFzomUnverified()))
                ->modalDescription(fn (): Htmlable => $this->fzomUnverifiedSampleDescription())
                ->modalSubmitActionLabel('Publish all, unverified')
                ->action(fn () => $this->startBulkPublish(BulkPublish::TYPE_FZOM_UNVERIFIED, $this->bulkCeiling)),
        ];
    }

    /**
     * @return list<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [BulkPublishProgress::class];
    }

    /**
     * Starts a bulk publish in the background (BulkPublish): thousands of
     * drafts do not fit in one request. With a `sync` queue the first chunk
     * is published right here and the progress widget's poll publishes the
     * rest; a small set is then done before the notification.
     *
     * @param  list<int>|null  $itemIds
     */
    public function startBulkPublish(string $type, ?int $upToId, ?array $itemIds = null): void
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->can('imports.manage')) {
            abort(403);
        }

        $bulk = app(BulkPublish::class);

        try {
            $operation = $bulk->start($type, $user, $upToId, $itemIds);
        } catch (BulkPublishAlreadyRunning) {
            Notification::make()
                ->title('Веќе тече едно објавување')
                ->body('Почекајте да заврши (напредокот е прикажан над табелата) или прекинете го таму.')
                ->warning()
                ->send();

            return;
        }

        if ($operation->driver === BulkOperation::DRIVER_INLINE) {
            $operation = $bulk->drive($operation, BulkPublish::requestBudget());
        }

        if ($operation->status === BulkOperationStatus::Completed) {
            Notification::make()->title('Објавувањето заврши')->body(BulkPublish::summary($operation))->success()->send();
        } else {
            Notification::make()
                ->title('Објавувањето започна…')
                ->body(sprintf('%s: %d профили се објавуваат во делови. Напредокот е прикажан над табелата; кога ќе заврши, ќе добиете известување.', BulkPublish::label($type), $operation->total))
                ->info()
                ->send();
        }

        $this->dispatch('bulk-publish-started');
    }

    #[On('bulk-publish-finished')]
    public function refreshAfterBulkPublish(): void
    {
        // Re-render: the table, the tab badges and the header actions'
        // counts change once the drafts are published.
    }

    /**
     * Every imported draft the verification engine verified (two sources
     * agree, docs/verification.md) — and a random sample of 20 to glance at.
     */
    private function sampleDescription(): Htmlable
    {
        $rows = app(VerifiedDraftPublisher::class)->sample(20)
            ->map(fn (ImportReviewItem $item): string => '<li>'.e($item->title).'</li>')
            ->implode('');

        return new HtmlString(
            '<p>Every hidden, never-published draft the verification engine verified becomes public. '
            .'Drafts with any other open item (possible duplicate, conflict, missing, uncertain), drafts the import marked as having no specialty, '
            .'and profiles staff unpublished stay hidden. A random sample to glance at:</p>'
            .'<ul style="margin-top:.5rem;list-style:disc;padding-inline-start:1.25rem;text-align:start">'.$rows.'</ul>'
        );
    }

    /**
     * Doctor drafts current in ФЗОМ with no licence on the Комора list and
     * no other open review item: they become public as „Неверификуван“.
     */
    private function fzomUnverifiedSampleDescription(): Htmlable
    {
        $rows = app(VerifiedDraftPublisher::class)->sampleFzomUnverified(20)
            ->map(fn (ImportReviewItem $item): string => '<li>'.e($item->title).'</li>')
            ->implode('');

        return new HtmlString(
            '<p>Doctors ФЗОМ lists today whose name has no licence on the Комора list become public, still „Неверификуван“. '
            .'Ambiguous names, disagreeing sources, specialty mismatches, website-only drafts, drafts without a specialty, drafts with any other open item '
            .'and profiles staff unpublished stay hidden. '
            .'A random sample to glance at:</p>'
            .'<ul style="margin-top:.5rem;list-style:disc;padding-inline-start:1.25rem;text-align:start">'.$rows.'</ul>'
        );
    }
}
