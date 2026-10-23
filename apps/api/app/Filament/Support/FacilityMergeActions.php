<?php

namespace App\Filament\Support;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Models\Facility;
use App\Models\ImportReviewItem;
use App\Models\User;
use App\Support\Import\Facilities\FacilityDuplicateMatcher;
use App\Support\Import\Facilities\FacilityDuplicatePlanner;
use App\Support\Import\Facilities\FacilityMerger;
use App\Support\Import\Facilities\FacilityMergeResult;
use App\Support\Import\Website\InstitutionsJsonImporter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * „Merge into register facility…“: a website-only facility (a draft of the
 * website import) is the register facility under another spelling.
 * FacilityMerger moves its doctors, images and empty-field values to the
 * register facility and retires it (docs/data-import.md §6a). On the
 * facility edit page, and on the `facility_duplicate` review items
 * (one, the suggested one, or the suggested one of each selected item).
 */
final class FacilityMergeActions
{
    /**
     * Edit page of a website-only facility.
     */
    public static function onFacility(): Action
    {
        return Action::make('mergeIntoRegisterFacility')
            ->label('Merge into register facility…')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('warning')
            ->visible(fn (Facility $record): bool => self::canManage() && ! $record->trashed() && FacilityMerger::isWebsiteOnly($record))
            ->schema(fn (Facility $record): array => [self::targetSelect($record)])
            ->modalDescription('The doctors, website images and the values this facility has and the register facility lacks move to the register facility; this draft is retired (restorable) and future imports of its website update the register facility. Fields the register facility already has, or that are locked, are not changed.')
            ->modalSubmitActionLabel('Merge')
            ->action(function (Facility $record, array $data, Action $action): void {
                $result = app(FacilityMerger::class)->merge((int) $record->getKey(), (int) $data['facility_id'], self::actor());
                self::notify($result);

                if ($result->status === FacilityMergeResult::MERGED) {
                    $action->redirect(FacilityResource::getUrl('edit', ['record' => (int) $data['facility_id']]));
                }
            });
    }

    /**
     * Review item: merge into the suggested register facility.
     */
    public static function mergeSuggested(): Action
    {
        return Action::make('mergeSuggestedFacility')
            ->label('Merge into suggested facility')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('success')
            ->visible(fn (ImportReviewItem $record): bool => self::isOpenDuplicate($record) && is_numeric($record->details['suggested_facility_id'] ?? null))
            ->requiresConfirmation()
            ->modalHeading('Merge into the suggested facility?')
            ->modalDescription(fn (ImportReviewItem $record): string => sprintf(
                '“%s” is merged into “%s” (%s). %s',
                $record->subject()->name ?? '?',
                $record->details['suggested_name'] ?? '?',
                $record->details['reasons'] ?? '',
                'Its doctors, website images and the values the register facility lacks move there; the draft is retired (restorable).',
            ))
            ->modalSubmitActionLabel('Merge')
            ->action(function (ImportReviewItem $record): void {
                self::notify(self::mergeItem($record, (int) $record->details['suggested_facility_id']));
            });
    }

    /**
     * Review item: pick the register facility (the candidates first, or any).
     */
    public static function mergeChosen(): Action
    {
        return Action::make('mergeIntoRegisterFacility')
            ->label('Merge into register facility…')
            ->icon('heroicon-o-building-office-2')
            ->visible(fn (ImportReviewItem $record): bool => self::isOpenDuplicate($record))
            ->schema(fn (ImportReviewItem $record): array => [self::targetSelect($record->subject(), $record)])
            ->modalSubmitActionLabel('Merge')
            ->action(function (ImportReviewItem $record, array $data): void {
                self::notify(self::mergeItem($record, (int) $data['facility_id']));
            });
    }

    /**
     * Review queue bulk action: each selected item into its suggestion.
     */
    public static function bulkMergeSuggested(): BulkAction
    {
        return BulkAction::make('mergeSuggestedFacilities')
            ->label('Merge suggested facilities')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Merge the suggested facilities?')
            ->modalDescription('Each selected “Same facility?” item with a suggestion is merged into its suggested register facility. Other items are left as they are. Check the suggestions first: medium and low ones need a look.')
            ->modalSubmitActionLabel('Merge')
            ->visible(fn (): bool => self::canManage())
            ->action(function (Collection $records): void {
                $counts = [FacilityMergeResult::MERGED => 0, FacilityMergeResult::ALREADY_MERGED => 0, FacilityMergeResult::REFUSED => 0];

                foreach ($records as $record) {
                    if ($record instanceof ImportReviewItem && self::isOpenDuplicate($record) && is_numeric($record->details['suggested_facility_id'] ?? null)) {
                        $counts[self::mergeItem($record, (int) $record->details['suggested_facility_id'])->status]++;
                    }
                }

                Notification::make()
                    ->title(sprintf('Merged %d, already merged %d, refused %d (open each refused one for the reason).', ...array_values($counts)))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function isDuplicateItem(ImportReviewItem $record): bool
    {
        return $record->kind === ImportReviewKind::Uncertain
            && $record->source === InstitutionsJsonImporter::SOURCE
            && ($record->details['reason'] ?? null) === FacilityDuplicatePlanner::REASON;
    }

    private static function isOpenDuplicate(ImportReviewItem $record): bool
    {
        return $record->status === ImportReviewStatus::Open && self::canManage() && self::isDuplicateItem($record);
    }

    private static function mergeItem(ImportReviewItem $record, int $intoId): FacilityMergeResult
    {
        $result = app(FacilityMerger::class)->merge((int) $record->subject_id, $intoId, self::actor());

        // The merge closes the facility's open items; one already merged
        // before is settled too.
        if ($result->status === FacilityMergeResult::ALREADY_MERGED) {
            $record->resolve(ImportReviewStatus::Resolved, FacilityMerger::RESOLUTION, self::actor());
        }

        return $result;
    }

    private static function targetSelect(?Facility $facility, ?ImportReviewItem $item = null): Select
    {
        $suggested = [];

        foreach ((array) ($item?->details['candidates'] ?? []) as $candidate) {
            if (is_array($candidate) && is_numeric($candidate['facility_id'] ?? null)) {
                $suggested[(int) $candidate['facility_id']] = self::label($candidate['name'] ?? '', $candidate['city'] ?? null).' · '.($candidate['confidence'] ?? '');
            }
        }

        if ($suggested === [] && $facility !== null) {
            foreach (app(FacilityDuplicateMatcher::class)->match($facility)['candidates'] as $candidate) {
                $suggested[(int) $candidate['facility_id']] = self::label((string) $candidate['name'], $candidate['city']).' · '.$candidate['confidence'];
            }
        }

        return Select::make('facility_id')
            ->label('Register facility')
            ->helperText('The suggestions first; type to search every register facility.')
            ->options($suggested)
            ->default(array_key_first($suggested))
            ->searchable()
            ->getSearchResultsUsing(fn (?string $search): array => $suggested + self::searchRegister($search))
            ->getOptionLabelUsing(fn ($value): ?string => $suggested[(int) $value] ?? self::optionLabel((int) $value))
            ->required();
    }

    /**
     * @return array<int, string>
     */
    private static function searchRegister(?string $search): array
    {
        $query = Facility::query()->clinical()
            ->where(fn ($inner) => $inner->whereNull('import_source')->orWhere('import_source', '!=', InstitutionsJsonImporter::SOURCE))
            ->orderBy('name')
            ->limit(50);

        if (filled($search)) {
            $query->searchName((string) $search);
        }

        return $query->get(['id', 'name', 'city'])
            ->mapWithKeys(fn (Facility $facility): array => [(int) $facility->getKey() => self::label((string) $facility->name, $facility->city)])
            ->all();
    }

    private static function optionLabel(int $id): ?string
    {
        $facility = Facility::query()->find($id, ['id', 'name', 'city']);

        return $facility !== null ? self::label((string) $facility->name, $facility->city) : null;
    }

    private static function label(string $name, ?string $city): string
    {
        return $name.($city !== null && $city !== '' ? ' — '.$city : '');
    }

    private static function notify(FacilityMergeResult $result): void
    {
        $notification = Notification::make()->title($result->message());
        $result->ok() ? $notification->success()->send() : $notification->warning()->send();
    }

    private static function canManage(): bool
    {
        return auth()->user()?->can('imports.manage') ?? false;
    }

    private static function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->can('imports.manage')) {
            abort(403);
        }

        return $user;
    }
}
