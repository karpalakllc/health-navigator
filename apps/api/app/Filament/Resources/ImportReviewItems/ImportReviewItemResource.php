<?php

namespace App\Filament\Resources\ImportReviewItems;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Filament\Resources\ImportReviewItems\Pages\ListImportReviewItems;
use App\Filament\Resources\ImportReviewItems\Pages\ViewImportReviewItem;
use App\Filament\Resources\ImportReviewItems\Widgets\BulkPublishProgress;
use App\Models\Doctor;
use App\Models\ImportReviewItem;
use App\Models\User;
use App\Support\Import\BulkPublish;
use App\Support\Import\ImportReviewActions;
use App\Support\Import\Names\DuplicateFinder;
use App\Support\Import\Names\NameCleanup;
use App\Support\Import\Names\NameReviewActions;
use App\Support\Import\Pharmacies\OnDutyLinker;
use App\Support\Import\Pharmacies\OnDutyPharmacyImporter;
use App\Support\Verification\Engine\EvidenceLoader;
use App\Support\Verification\Engine\VerifiedDraftPublisher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * The import review queue (Data import): drafts to publish, values that
 * changed on live profiles, conflicts with staff edits, records the source
 * dropped, and rows the importer could not place.
 */
class ImportReviewItemResource extends Resource
{
    protected static ?string $model = ImportReviewItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $navigationLabel = 'Import review';

    protected static string|\UnitEnum|null $navigationGroup = 'Data import';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'import review item';

    protected static ?string $pluralModelLabel = 'import review';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = ImportReviewItem::query()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('kind')->label('Kind')->badge()->formatStateUsing(fn (ImportReviewKind $state): string => $state->label()),
            TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (ImportReviewStatus $state): string => $state->label()),
            TextEntry::make('source')->label('Source'),
            TextEntry::make('title')->label('Subject')->columnSpanFull(),
            TextEntry::make('guidance')
                ->label('Guidance')
                ->state(fn (ImportReviewItem $record): string => self::guidance($record))
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('subject_link')
                ->label('Profile')
                ->state(fn (ImportReviewItem $record): string => self::subjectLabel($record))
                ->url(fn (ImportReviewItem $record): ?string => self::subjectUrl($record)),
            TextEntry::make('run.id')->label('Import run')->placeholder('—'),
            KeyValueEntry::make('details')
                ->label('Details')
                ->state(fn (ImportReviewItem $record): array => self::flatDetails($record->details ?? []))
                ->columnSpanFull(),
            TextEntry::make('resolution')->label('Resolution')->placeholder('—'),
            TextEntry::make('resolvedBy.name')->label('Resolved by')->placeholder('—'),
            TextEntry::make('resolved_at')->label('Resolved')->dateTime()->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')
                    ->label('Kind')
                    ->badge()
                    ->formatStateUsing(fn (ImportReviewKind $state): string => $state->label())
                    ->color(fn (ImportReviewKind $state): string => match ($state) {
                        ImportReviewKind::New => 'info',
                        ImportReviewKind::Changed => 'gray',
                        ImportReviewKind::Conflict, ImportReviewKind::Missing => 'warning',
                        ImportReviewKind::Unmatched => 'danger',
                        ImportReviewKind::Uncertain => 'primary',
                    }),
                // One growing text column: short title + one-line summary.
                // Full prose belongs on the View page — never in two wrap() cells.
                TextColumn::make('title')
                    ->label('Subject')
                    ->searchable()
                    ->state(fn (ImportReviewItem $record): string => self::listTitle($record))
                    ->description(fn (ImportReviewItem $record): string => self::summary($record))
                    ->tooltip(fn (ImportReviewItem $record): string => self::listTitle($record))
                    ->limit(96)
                    ->wrap()
                    ->extraCellAttributes(['class' => 'min-w-72 max-w-2xl']),
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (mixed $state): string => match ((string) $state) {
                        'cleanup' => 'cleanup',
                        'verification' => 'verification',
                        'fzom' => 'ФЗОМ',
                        'website' => 'website',
                        default => (string) ($state ?: '—'),
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ImportReviewStatus $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Raised')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            // What decides the most first (the verification engine ranks by
            // impact: published profiles, many doctors behind one decision).
            ->defaultSort(fn ($query) => $query->orderByDesc('priority')->orderByDesc('created_at'))
            ->modifyQueryUsing(fn ($query) => $query->with('run'))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ImportReviewStatus::cases())->mapWithKeys(fn (ImportReviewStatus $s) => [$s->value => $s->label()])->all())
                    ->default(ImportReviewStatus::Open->value),
                SelectFilter::make('source')
                    ->label('Source')
                    ->options(fn (): array => ImportReviewItem::query()->distinct()->orderBy('source')->pluck('source', 'source')->all()),
                SelectFilter::make('subject_type')
                    ->label('Record')
                    ->options(['doctor' => 'Doctor', 'facility' => 'Facility']),
            ])
            ->recordActions([
                ViewAction::make(),
                self::publishAction(),
                self::acceptAction(),
                self::keepAction(),
                self::hideAction(),
                self::trustSiteAction(),
                self::acceptSuggestionAction(),
                self::mergeDuplicatesAction(),
                self::keepNameAction(),
                self::linkSuggestedPharmacyAction(),
                self::linkPharmacyAction(),
                self::dismissAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Publish selected drafts')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Publish selected drafts?')
                        ->modalDescription('Publishes the doctor and facility profiles of the selected "new" items. Check each one first: imported drafts are not verified.')
                        ->modalSubmitActionLabel('Publish')
                        ->visible(fn (): bool => self::canManage())
                        // Keys only: "select all" can mean thousands of rows.
                        ->fetchSelectedRecords(false)
                        ->action(function (Collection $records, ListImportReviewItems $livewire): void {
                            $ids = $records->map(fn ($key): int => (int) $key)->values()->all();

                            // A large selection goes to the background, like
                            // "Publish all verified" (one request runs out of
                            // time after a few thousand).
                            if (count($ids) > app(BulkPublish::class)->selectionInlineLimit) {
                                $livewire->startBulkPublish(BulkPublish::TYPE_SELECTED, null, $ids);

                                return;
                            }

                            $published = app(VerifiedDraftPublisher::class)->publishChunk($ids, self::actor())['published'];

                            Notification::make()->title("Published {$published} profiles")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('linkSuggestedPharmacies')
                        ->label('Link suggested pharmacies')
                        ->icon('heroicon-o-link')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Link the suggested pharmacies?')
                        ->modalDescription('For each selected on-duty pharmacy with a suggestion, links every month of its schedule to the suggested pharmacy and remembers the link. Other selected items are left as they are.')
                        ->modalSubmitActionLabel('Link')
                        ->visible(fn (): bool => self::canManage())
                        ->action(function (Collection $records): void {
                            $linker = app(OnDutyLinker::class);
                            $linked = 0;

                            foreach ($records as $record) {
                                if (self::hasPharmacySuggestion($record) && $linker->linkByStaff($record, (int) $record->details['suggested_facility_id'], self::actor())) {
                                    $linked++;
                                }
                            }

                            Notification::make()->title("Linked {$linked} on-duty pharmacies")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('dismiss')
                        ->label('Dismiss selected')
                        ->icon('heroicon-o-x-mark')
                        ->requiresConfirmation()
                        ->modalHeading('Dismiss selected?')
                        ->modalSubmitActionLabel('Dismiss')
                        ->visible(fn (): bool => self::canManage())
                        ->fetchSelectedRecords(false)
                        ->action(function (Collection $records): void {
                            // Set-based, a chunk at a time (as resolve() does
                            // per item): fits one request for any selection.
                            $by = self::actor();

                            foreach ($records->chunk(500) as $chunk) {
                                ImportReviewItem::query()->whereKey($chunk->all())->open()->update([
                                    'status' => ImportReviewStatus::Dismissed->value,
                                    'resolution' => 'dismissed',
                                    'resolved_by_id' => $by->getKey(),
                                    'resolved_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Publish')
            ->icon('heroicon-o-eye')
            ->color('success')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->kind === ImportReviewKind::New)
            ->requiresConfirmation()
            ->modalHeading('Publish profile?')
            ->modalDescription('The profile becomes public. Imported drafts are not verified: check name, workplace and specialty.')
            ->modalSubmitActionLabel('Publish')
            ->action(function (ImportReviewItem $record): void {
                if (self::open($record) && app(ImportReviewActions::class)->publish($record, self::actor())) {
                    Notification::make()->title('Published')->success()->send();
                }
            });
    }

    public static function acceptAction(): Action
    {
        return Action::make('accept')
            ->label('Use imported value')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->kind === ImportReviewKind::Conflict && isset($record->details['field']))
            ->requiresConfirmation()
            ->modalHeading('Replace with the imported value?')
            ->modalDescription(fn (ImportReviewItem $record): string => sprintf('Replace “%s” with “%s”.', $record->details['current'] ?? '—', $record->details['incoming'] ?? '—'))
            ->modalSubmitActionLabel('Replace')
            ->action(function (ImportReviewItem $record): void {
                if (self::open($record) && ! app(ImportReviewActions::class)->acceptIncoming($record, self::actor())) {
                    Notification::make()->title('Not applied: the field is locked, the profile changed, or this field cannot be set from the queue. Edit the profile.')->warning()->send();
                }
            });
    }

    public static function keepAction(): Action
    {
        return Action::make('keep')
            ->label('Keep current and lock')
            ->icon('heroicon-o-lock-closed')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->kind === ImportReviewKind::Conflict && isset($record->details['field']))
            ->requiresConfirmation()
            ->modalHeading('Keep the current value?')
            ->modalDescription('The current value stays and the field is locked against future imports (unlock it on the profile).')
            ->modalSubmitActionLabel('Keep')
            ->action(fn (ImportReviewItem $record) => self::open($record) ? app(ImportReviewActions::class)->keepCurrent($record, self::actor()) : null);
    }

    public static function hideAction(): Action
    {
        return Action::make('hide')
            ->label('Hide profile')
            ->icon('heroicon-o-eye-slash')
            ->color('danger')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->kind === ImportReviewKind::Missing)
            ->requiresConfirmation()
            ->modalHeading('Hide profile?')
            ->modalDescription('The profile is withdrawn from public view. Nothing is deleted; reviews stay.')
            ->modalSubmitActionLabel('Hide')
            ->action(fn (ImportReviewItem $record) => self::open($record) ? app(ImportReviewActions::class)->hide($record, self::actor()) : null);
    }

    /**
     * A website flagged as compromised or stale (verification engine): staff
     * looked and its staff list is current. The engine then counts it as
     * evidence again on its next run; the decision sticks.
     */
    public static function trustSiteAction(): Action
    {
        return Action::make('trustSite')
            ->label('Trust this website')
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->kind === ImportReviewKind::Uncertain
                && ($record->details['reason'] ?? null) === 'flagged_source')
            ->requiresConfirmation()
            ->modalHeading('Trust this website?')
            ->modalDescription('Its staff list counts again as verification evidence for every doctor who lists it. Do this only after you have checked the page is current and clean.')
            ->modalSubmitActionLabel('Trust')
            ->action(function (ImportReviewItem $record): void {
                if (self::open($record) && $record->resolve(ImportReviewStatus::Resolved, EvidenceLoader::TRUSTED_RESOLUTION, self::actor())) {
                    Notification::make()->title('Trusted. The next verification run (nightly, or import:adjudicate) uses it.')->success()->send();
                }
            });
    }

    /**
     * Name cleanup (import:clean-names): set the proposed value.
     */
    public static function acceptSuggestionAction(): Action
    {
        return Action::make('acceptSuggestion')
            ->label('Accept suggestion')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && NameReviewActions::hasSuggestion($record))
            ->requiresConfirmation()
            ->modalDescription(fn (ImportReviewItem $record): string => isset($record->details['changes'])
                ? (string) ($record->details['proposals'] ?? '')
                : sprintf('“%s” → “%s”', $record->details['current'] ?? '—', $record->details['suggestion'] ?? '—'))
            ->action(function (ImportReviewItem $record): void {
                if (self::open($record) && ! app(NameReviewActions::class)->accept($record, self::actor())) {
                    Notification::make()->title('Not applied: the field is locked or changed since. Edit the profile.')->warning()->send();
                }
            });
    }

    /**
     * Name cleanup: merge a group of duplicate website drafts.
     */
    public static function mergeDuplicatesAction(): Action
    {
        return Action::make('mergeDuplicates')
            ->label('Merge')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('success')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && NameReviewActions::isMergeGroup($record))
            ->requiresConfirmation()
            ->modalDescription(fn (ImportReviewItem $record): string => (string) ($record->details['action'] ?? ''))
            ->action(function (ImportReviewItem $record): void {
                if (! self::open($record)) {
                    return;
                }

                $result = app(NameReviewActions::class)->merge($record, self::actor());
                Notification::make()->title("Merged {$result['merged']}, skipped {$result['skipped']} (no longer a hidden draft, or gone).")->success()->send();
            });
    }

    /**
     * Name cleanup: keep the current value (or keep duplicates apart).
     */
    public static function keepNameAction(): Action
    {
        return Action::make('keepName')
            ->label('Keep apart')
            ->icon('heroicon-o-hand-raised')
            ->color('gray')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->source === NameCleanup::SOURCE)
            ->action(fn (ImportReviewItem $record) => self::open($record) ? app(NameReviewActions::class)->keep($record, self::actor()) : null);
    }

    /**
     * On-duty schedule (OnDutyLinker): one pharmacy with the same name
     * words and no address to confirm it.
     */
    public static function linkSuggestedPharmacyAction(): Action
    {
        return Action::make('linkSuggestedPharmacy')
            ->label('Link suggested pharmacy')
            ->icon('heroicon-o-link')
            ->color('success')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && self::hasPharmacySuggestion($record))
            ->requiresConfirmation()
            ->modalDescription(fn (ImportReviewItem $record): string => sprintf('“%s” (%s) is “%s”. Every month of its schedule is linked, and the link is remembered.', $record->details['name'] ?? '?', $record->details['town'] ?? '?', $record->details['suggested_name'] ?? '?'))
            ->modalSubmitActionLabel('Link')
            ->action(function (ImportReviewItem $record): void {
                if (self::open($record) && ! app(OnDutyLinker::class)->linkByStaff($record, (int) $record->details['suggested_facility_id'], self::actor())) {
                    Notification::make()->title('Not linked: the suggested pharmacy is gone. Pick one with “Link to pharmacy”.')->warning()->send();
                }
            });
    }

    /**
     * On-duty schedule: staff pick the pharmacy among those of the town.
     */
    public static function linkPharmacyAction(): Action
    {
        return Action::make('linkPharmacy')
            ->label('Link to pharmacy')
            ->icon('heroicon-o-building-storefront')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && self::isOnDutyPharmacy($record))
            ->schema(fn (ImportReviewItem $record): array => [
                Select::make('facility_id')
                    ->label('Pharmacy in '.($record->details['town'] ?? 'this town'))
                    ->options(fn (): array => app(OnDutyLinker::class)->optionsFor($record))
                    ->default($record->details['suggested_facility_id'] ?? (($record->details['candidate_facility_ids'] ?? [])[0] ?? null))
                    ->searchable()
                    ->required(),
            ])
            ->modalDescription('Every month of this on-duty pharmacy\'s schedule is linked to the pharmacy you pick, and the link is remembered for the next schedules.')
            ->modalSubmitActionLabel('Link')
            ->action(function (ImportReviewItem $record, array $data): void {
                if (self::open($record) && ! app(OnDutyLinker::class)->linkByStaff($record, (int) $data['facility_id'], self::actor())) {
                    Notification::make()->title('Not linked: that pharmacy no longer exists.')->warning()->send();
                }
            });
    }

    private static function isOnDutyPharmacy(ImportReviewItem $record): bool
    {
        return $record->source === OnDutyPharmacyImporter::SOURCE
            && $record->kind === ImportReviewKind::Unmatched
            && str_starts_with($record->item_key, 'on-duty-pharmacy:');
    }

    private static function hasPharmacySuggestion(ImportReviewItem $record): bool
    {
        return self::isOnDutyPharmacy($record) && $record->status === ImportReviewStatus::Open
            && is_numeric($record->details['suggested_facility_id'] ?? null);
    }

    public static function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label(fn (ImportReviewItem $record): string => $record->kind === ImportReviewKind::Changed ? 'Mark seen' : 'Dismiss')
            ->icon('heroicon-o-check')
            ->color('gray')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->source !== NameCleanup::SOURCE)
            ->action(fn (ImportReviewItem $record) => self::open($record) ? app(ImportReviewActions::class)->dismiss($record, self::actor()) : null);
    }

    private static function open(ImportReviewItem $record): bool
    {
        return $record->status === ImportReviewStatus::Open && self::canManage();
    }

    private static function canManage(): bool
    {
        return auth()->user()?->can('imports.manage') ?? false;
    }

    private static function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    public static function subjectLabel(ImportReviewItem $record): string
    {
        $subject = $record->subject();

        return match (true) {
            $subject === null => '—',
            $subject instanceof Doctor => $subject->full_name.($subject->is_published ? ' (public)' : ' (hidden draft)'),
            default => $subject->name.($subject->is_published ? ' (public)' : ' (hidden draft)'),
        };
    }

    /**
     * The profile's edit page, for staff who may edit it and while it exists
     * (imports.view alone would get a 403 there).
     */
    public static function subjectUrl(ImportReviewItem $record): ?string
    {
        $subject = $record->subject();

        if ($subject === null || $subject->trashed() || ! (auth()->user()?->can('update', $subject) ?? false)) {
            return null;
        }

        return match ($record->subject_type) {
            'doctor' => $record->subject_id !== null ? DoctorResource::getUrl('edit', ['record' => $record->subject_id]) : null,
            'facility' => $record->subject_id !== null ? FacilityResource::getUrl('edit', ['record' => $record->subject_id]) : null,
            default => null,
        };
    }

    /**
     * Compact list title: structured duplicate/name rows stay scannable;
     * legacy long titles from older runs are rewritten from details.
     */
    public static function listTitle(ImportReviewItem $record): string
    {
        $details = $record->details ?? [];

        if (($details['reason'] ?? null) === DuplicateFinder::REASON || isset($details['pairs'])) {
            $count = (int) ($details['count'] ?? count($details['pairs'] ?? []));
            $facility = (string) ($details['facility_name'] ?? self::facilityFromTitle($record->title));
            $elsewhere = (int) ($details['other_town'] ?? 0);
            $kind = (string) ($details['kind'] ?? '');

            if ($kind === 'namesakes' || isset($details['candidate_doctor_ids'])) {
                $ids = (array) ($details['candidate_doctor_ids'] ?? []);

                return sprintf('Same name · %d profiles', $count > 0 ? $count : count($ids));
            }

            $suffix = $elsewhere > 0 ? sprintf(' · %d in another town', $elsewhere) : '';

            return $facility !== ''
                ? sprintf('Possible duplicates · %d · %s%s', $count, $facility, $suffix)
                : sprintf('Possible duplicates · %d%s', $count, $suffix);
        }

        if (($details['reason'] ?? null) === NameCleanup::REASON && isset($details['changes']) && is_array($details['changes'])) {
            return sprintf('%d names · %s', count($details['changes']), NameCleanup::PROBLEMS[$details['problem'] ?? ''] ?? ($details['problem'] ?? 'cleanup'));
        }

        return $record->title;
    }

    /**
     * One short line under the title — never the multi-sentence `action` prose.
     */
    public static function summary(ImportReviewItem $record): string
    {
        $details = $record->details ?? [];

        return match ($record->kind) {
            ImportReviewKind::Conflict => sprintf('%s: “%s” → “%s”', $details['field'] ?? '?', $details['current'] ?? '—', $details['incoming'] ?? '—'),
            ImportReviewKind::Changed => implode(', ', array_keys((array) ($details['fields'] ?? []))) ?: '—',
            ImportReviewKind::Missing => sprintf('Absent %s runs%s', $details['runs'] ?? '?', ($details['published'] ?? false) ? ', public' : ''),
            ImportReviewKind::Unmatched => self::reasonLabel((string) ($details['reason'] ?? 'unmatched')),
            ImportReviewKind::New => ($details['needs_licence_verification'] ?? false)
                ? 'Verify licence before publishing'
                : (string) ($details['source_url'] ?? '—'),
            ImportReviewKind::Uncertain => self::uncertainSummary($details),
        };
    }

    /**
     * Full guidance for the View page (English for known shapes; stored text otherwise).
     */
    public static function guidance(ImportReviewItem $record): string
    {
        $details = $record->details ?? [];
        $structured = self::structuredGuidance($details);

        if ($structured !== null) {
            return $structured;
        }

        $action = trim((string) ($details['action'] ?? $details['hint'] ?? ''));

        return $action !== '' ? $action : self::summary($record);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function uncertainSummary(array $details): string
    {
        if (($details['reason'] ?? null) === DuplicateFinder::REASON || isset($details['pairs'])) {
            $kind = (string) ($details['kind'] ?? '');

            return match ($kind) {
                'website_into_register' => 'Merge into ФЗОМ profile, or keep apart',
                'website_twins' => 'Merge website drafts, or keep apart',
                'namesakes' => 'Compare namesakes · merge only by hand',
                default => 'Merge / Keep apart',
            };
        }

        if (($details['reason'] ?? null) === NameCleanup::REASON) {
            if (isset($details['suggestion']) && is_string($details['suggestion']) && $details['suggestion'] !== '') {
                return 'Accept suggestion, or keep apart';
            }

            if (isset($details['changes'])) {
                return 'Accept suggestions, or keep apart';
            }

            return NameCleanup::PROBLEMS[$details['problem'] ?? ''] ?? 'Edit the profile if needed';
        }

        if (($details['reason'] ?? null) === 'flagged_source') {
            return 'Trust the website if it is current and clean';
        }

        // Never dump multi-sentence `action` prose into the list description.
        $reason = trim((string) ($details['reason'] ?? ''));

        return $reason !== '' ? self::reasonLabel($reason) : 'Open for details';
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function structuredGuidance(array $details): ?string
    {
        if (($details['reason'] ?? null) === DuplicateFinder::REASON || isset($details['pairs'])) {
            $elsewhere = (int) ($details['other_town'] ?? 0);

            return match ((string) ($details['kind'] ?? '')) {
                'website_into_register' => 'Each draft has the name of exactly one ФЗОМ doctor in the register'
                    .($elsewhere > 0 ? " ({$elsewhere} in another town)" : '')
                    .'. “Merge” moves the workplace (and title/specialties when the profile has none) into the ФЗОМ profile and deletes the draft; “Keep apart” leaves them separate.',
                'website_twins' => 'Same person on two sites? “Merge” moves the workplace into the other profile (the public one, or the oldest draft) and deletes the draft; “Keep apart” leaves them separate.',
                'namesakes' => 'Several profiles with this name (possible namesakes): compare them; merge by hand only if they are the same person, or “Keep apart”.',
                default => null,
            };
        }

        if (($details['reason'] ?? null) === NameCleanup::REASON) {
            $problem = NameCleanup::PROBLEMS[$details['problem'] ?? ''] ?? null;

            if ($problem === null) {
                return null;
            }

            if (isset($details['changes'])) {
                return $problem.' “Accept suggestion” applies every proposal (a profile changed since is skipped); “Keep apart” keeps them all.';
            }

            return isset($details['suggestion']) && is_string($details['suggestion']) && $details['suggestion'] !== ''
                ? $problem.' “Accept suggestion” sets the proposed value.'
                : $problem.' Edit the profile if it needs a change.';
        }

        return null;
    }

    private static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'flagged_source' => 'Flagged source',
            OnDutyLinker::REASON_SUGGESTED => 'Probable match · link or dismiss',
            OnDutyLinker::REASON_AMBIGUOUS => 'Several pharmacies match',
            OnDutyLinker::REASON_MISSING => 'Pharmacy not in the directory',
            'code_on_other_facility' => 'ФЗО code on a non-pharmacy facility',
            'possible_duplicate' => 'Possible duplicate',
            'name_cleanup' => 'Name cleanup',
            'unmatched', '' => 'Unmatched',
            default => str_replace('_', ' ', $reason),
        };
    }

    private static function facilityFromTitle(string $title): string
    {
        if (preg_match('/„([^“]+)“/u', $title, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, string>
     */
    private static function flatDetails(array $details, string $prefix = ''): array
    {
        $flat = [];

        foreach ($details as $key => $value) {
            $name = $prefix.$key;

            if (is_array($value) && ! array_is_list($value)) {
                $flat += self::flatDetails($value, $name.'.');
            } else {
                $flat[$name] = match (true) {
                    // A list of pairs or records (name cleanup): one JSON line.
                    is_array($value) && array_filter($value, 'is_array') !== [] => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
                    is_array($value) => implode(', ', array_map('strval', $value)),
                    is_bool($value) => $value ? 'yes' : 'no',
                    default => (string) $value,
                };
            }
        }

        return $flat;
    }

    /**
     * @return list<class-string>
     */
    public static function getWidgets(): array
    {
        return [BulkPublishProgress::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportReviewItems::route('/'),
            'view' => ViewImportReviewItem::route('/{record}'),
        ];
    }
}
