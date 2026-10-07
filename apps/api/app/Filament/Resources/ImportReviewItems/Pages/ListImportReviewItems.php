<?php

namespace App\Filament\Resources\ImportReviewItems\Pages;

use App\Enums\ImportReviewKind;
use App\Filament\Resources\ImportReviewItems\ImportReviewItemResource;
use App\Models\ImportReviewItem;
use App\Models\User;
use App\Support\Verification\Engine\VerifiedDraftPublisher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListImportReviewItems extends ListRecords
{
    protected static string $resource = ImportReviewItemResource::class;

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
                ->label('Објави ги сите верифицирани')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => (auth()->user()?->can('imports.manage') ?? false) && app(VerifiedDraftPublisher::class)->count() > 0)
                ->requiresConfirmation()
                ->modalHeading(fn (): string => sprintf('Publish %d verified drafts?', app(VerifiedDraftPublisher::class)->count()))
                ->modalDescription(fn (): Htmlable => $this->sampleDescription())
                ->modalSubmitActionLabel('Publish all')
                ->action(function (): void {
                    $user = auth()->user();

                    if (! $user instanceof User || ! $user->can('imports.manage')) {
                        abort(403);
                    }

                    $published = app(VerifiedDraftPublisher::class)->publishAll($user);
                    Notification::make()->title("Published {$published} verified profiles")->success()->send();
                }),
            // Owner's decision: ФЗОМ lists them today, the Комора list has no
            // licence of their name, nothing else is open on them → public,
            // but „Неверифициран“ (verified later automatically if a licence
            // appears).
            Action::make('publishFzomUnverified')
                ->label('Објави ги и неверифицираните од ФЗОМ')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->visible(fn (): bool => (auth()->user()?->can('imports.manage') ?? false) && app(VerifiedDraftPublisher::class)->countFzomUnverified() > 0)
                ->requiresConfirmation()
                ->modalHeading(fn (): string => sprintf('Publish %d unverified ФЗОМ drafts?', app(VerifiedDraftPublisher::class)->countFzomUnverified()))
                ->modalDescription(fn (): Htmlable => $this->fzomUnverifiedSampleDescription())
                ->modalSubmitActionLabel('Publish all, unverified')
                ->action(function (): void {
                    $user = auth()->user();

                    if (! $user instanceof User || ! $user->can('imports.manage')) {
                        abort(403);
                    }

                    $published = app(VerifiedDraftPublisher::class)->publishAllFzomUnverified($user);
                    Notification::make()->title("Published {$published} unverified ФЗОМ profiles")->success()->send();
                }),
        ];
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
            '<p>Every hidden draft the verification engine verified becomes public. A random sample to glance at:</p>'
            .'<ul style="margin-top:.5rem;list-style:disc;padding-inline-start:1.25rem;text-align:start">'.$rows.'</ul>'
        );
    }

    /**
     * Doctor drafts current in ФЗОМ with no licence on the Комора list and
     * no other open review item: they become public as „Неверифициран“.
     */
    private function fzomUnverifiedSampleDescription(): Htmlable
    {
        $rows = app(VerifiedDraftPublisher::class)->sampleFzomUnverified(20)
            ->map(fn (ImportReviewItem $item): string => '<li>'.e($item->title).'</li>')
            ->implode('');

        return new HtmlString(
            '<p>Doctors ФЗОМ lists today whose name has no licence on the Комора list become public, still „Неверифициран“. '
            .'Ambiguous names, disagreeing sources, specialty mismatches, website-only drafts and drafts with any other open item stay hidden. '
            .'A random sample to glance at:</p>'
            .'<ul style="margin-top:.5rem;list-style:disc;padding-inline-start:1.25rem;text-align:start">'.$rows.'</ul>'
        );
    }
}
