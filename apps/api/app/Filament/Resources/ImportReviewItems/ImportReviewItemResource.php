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
use App\Support\Verification\Engine\EvidenceLoader;
use App\Support\Verification\Engine\VerifiedDraftPublisher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
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
 * The import review queue („Увоз на податоци“): drafts to publish, values
 * that changed on live profiles, conflicts with staff edits, records the
 * source dropped, and rows the importer could not place.
 */
class ImportReviewItemResource extends Resource
{
    protected static ?string $model = ImportReviewItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $navigationLabel = 'Преглед на увоз';

    protected static string|\UnitEnum|null $navigationGroup = 'Увоз на податоци';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'ставка за преглед';

    protected static ?string $pluralModelLabel = 'преглед на увоз';

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
            TextEntry::make('kind')->label('Вид')->badge()->formatStateUsing(fn (ImportReviewKind $state): string => $state->label()),
            TextEntry::make('status')->label('Статус')->badge()->formatStateUsing(fn (ImportReviewStatus $state): string => $state->label()),
            TextEntry::make('source')->label('Извор'),
            TextEntry::make('title')->label('Предмет')->columnSpanFull(),
            TextEntry::make('guidance')
                ->label('Упатство')
                ->state(fn (ImportReviewItem $record): string => self::guidance($record))
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('subject_link')
                ->label('Профил')
                ->state(fn (ImportReviewItem $record): string => self::subjectLabel($record))
                ->url(fn (ImportReviewItem $record): ?string => self::subjectUrl($record)),
            TextEntry::make('run.id')->label('Увоз')->placeholder('—'),
            KeyValueEntry::make('details')
                ->label('Детали')
                ->state(fn (ImportReviewItem $record): array => self::flatDetails($record->details ?? []))
                ->columnSpanFull(),
            TextEntry::make('resolution')->label('Резолуција')->placeholder('—'),
            TextEntry::make('resolvedBy.name')->label('Решил')->placeholder('—'),
            TextEntry::make('resolved_at')->label('Решено')->dateTime()->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')
                    ->label('Вид')
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
                    ->label('Предмет')
                    ->searchable()
                    ->state(fn (ImportReviewItem $record): string => self::listTitle($record))
                    ->description(fn (ImportReviewItem $record): string => self::summary($record))
                    ->tooltip(fn (ImportReviewItem $record): string => $record->title)
                    ->limit(96)
                    ->wrap()
                    ->extraCellAttributes(['class' => 'min-w-72 max-w-2xl']),
                TextColumn::make('source')
                    ->label('Извор')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (ImportReviewStatus $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('priority')
                    ->label('Приоритет')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Подигнато')
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
                    ->label('Статус')
                    ->options(collect(ImportReviewStatus::cases())->mapWithKeys(fn (ImportReviewStatus $s) => [$s->value => $s->label()])->all())
                    ->default(ImportReviewStatus::Open->value),
                SelectFilter::make('source')
                    ->label('Извор')
                    ->options(fn (): array => ImportReviewItem::query()->distinct()->orderBy('source')->pluck('source', 'source')->all()),
                SelectFilter::make('subject_type')
                    ->label('Запис')
                    ->options(['doctor' => 'Лекар', 'facility' => 'Установа']),
            ])
            ->recordActions([
                ViewAction::make()->label('Преглед'),
                self::publishAction(),
                self::acceptAction(),
                self::keepAction(),
                self::hideAction(),
                self::trustSiteAction(),
                self::acceptSuggestionAction(),
                self::mergeDuplicatesAction(),
                self::keepNameAction(),
                self::dismissAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Објави избраните нацрти')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Објави избраните нацрти?')
                        ->modalDescription('Се објавуваат профилите на избраните „нови“ ставки. Проверете ги прво: увезените нацрти не се верификувани.')
                        ->modalSubmitActionLabel('Објави')
                        ->visible(fn (): bool => self::canManage())
                        // Keys only: "select all" can mean thousands of rows.
                        ->fetchSelectedRecords(false)
                        ->action(function (Collection $records, ListImportReviewItems $livewire): void {
                            $ids = $records->map(fn ($key): int => (int) $key)->values()->all();

                            // A large selection goes to the background, like
                            // „Објави ги сите верификувани“ (one request runs
                            // out of time after a few thousand).
                            if (count($ids) > app(BulkPublish::class)->selectionInlineLimit) {
                                $livewire->startBulkPublish(BulkPublish::TYPE_SELECTED, null, $ids);

                                return;
                            }

                            $published = app(VerifiedDraftPublisher::class)->publishChunk($ids, self::actor())['published'];

                            Notification::make()->title("Објавени {$published} профили")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('dismiss')
                        ->label('Отфрли избрани')
                        ->icon('heroicon-o-x-mark')
                        ->requiresConfirmation()
                        ->modalHeading('Отфрли избрани?')
                        ->modalSubmitActionLabel('Отфрли')
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
            ->label('Објави')
            ->icon('heroicon-o-eye')
            ->color('success')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->kind === ImportReviewKind::New)
            ->requiresConfirmation()
            ->modalHeading('Објави профил?')
            ->modalDescription('Профилот станува јавен. Увезените нацрти не се верификувани: проверете име, работно место и специјалност.')
            ->modalSubmitActionLabel('Објави')
            ->action(function (ImportReviewItem $record): void {
                if (self::open($record) && app(ImportReviewActions::class)->publish($record, self::actor())) {
                    Notification::make()->title('Објавено')->success()->send();
                }
            });
    }

    public static function acceptAction(): Action
    {
        return Action::make('accept')
            ->label('Користи увезена вредност')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->kind === ImportReviewKind::Conflict && isset($record->details['field']))
            ->requiresConfirmation()
            ->modalHeading('Замени со увезената вредност?')
            ->modalDescription(fn (ImportReviewItem $record): string => sprintf('Замени „%s“ со „%s“.', $record->details['current'] ?? '—', $record->details['incoming'] ?? '—'))
            ->modalSubmitActionLabel('Замени')
            ->action(function (ImportReviewItem $record): void {
                if (self::open($record) && ! app(ImportReviewActions::class)->acceptIncoming($record, self::actor())) {
                    Notification::make()->title('Не е применето: полето е заклучено, профилот е изменет, или ова поле не може да се постави од редот. Уредете го профилот.')->warning()->send();
                }
            });
    }

    public static function keepAction(): Action
    {
        return Action::make('keep')
            ->label('Задржи и заклучи')
            ->icon('heroicon-o-lock-closed')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->kind === ImportReviewKind::Conflict && isset($record->details['field']))
            ->requiresConfirmation()
            ->modalHeading('Задржи ја тековната вредност?')
            ->modalDescription('Тековната вредност останува и полето се заклучува против идни увози (отклучете го на профилот).')
            ->modalSubmitActionLabel('Задржи')
            ->action(fn (ImportReviewItem $record) => self::open($record) ? app(ImportReviewActions::class)->keepCurrent($record, self::actor()) : null);
    }

    public static function hideAction(): Action
    {
        return Action::make('hide')
            ->label('Сокриј профил')
            ->icon('heroicon-o-eye-slash')
            ->color('danger')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->kind === ImportReviewKind::Missing)
            ->requiresConfirmation()
            ->modalHeading('Сокриј профил?')
            ->modalDescription('Профилот се повлекува од јавност. Ништо не се брише; оценките остануваат.')
            ->modalSubmitActionLabel('Сокриј')
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
            ->label('Довери ја страницата')
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->kind === ImportReviewKind::Uncertain
                && ($record->details['reason'] ?? null) === 'flagged_source')
            ->requiresConfirmation()
            ->modalHeading('Довери ја оваа страница?')
            ->modalDescription('Нејзиниот список на персонал повторно важи како доказ за верификација, за секој лекар што го наведува. Направете го ова само откако ќе проверите дека страницата е тековна и чиста.')
            ->modalSubmitActionLabel('Довери')
            ->action(function (ImportReviewItem $record): void {
                if (self::open($record) && $record->resolve(ImportReviewStatus::Resolved, EvidenceLoader::TRUSTED_RESOLUTION, self::actor())) {
                    Notification::make()->title('Доверено. Следниот тек на верификација (ноќен, или import:adjudicate) ја користи.')->success()->send();
                }
            });
    }

    /**
     * Name cleanup (import:clean-names): set the proposed value.
     */
    public static function acceptSuggestionAction(): Action
    {
        return Action::make('acceptSuggestion')
            ->label('Прифати предлог')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && NameReviewActions::hasSuggestion($record))
            ->requiresConfirmation()
            ->modalDescription(fn (ImportReviewItem $record): string => isset($record->details['changes'])
                ? (string) ($record->details['proposals'] ?? '')
                : sprintf('„%s“ → „%s“', $record->details['current'] ?? '—', $record->details['suggestion'] ?? '—'))
            ->action(function (ImportReviewItem $record): void {
                if (self::open($record) && ! app(NameReviewActions::class)->accept($record, self::actor())) {
                    Notification::make()->title('Не е применето: полето е заклучено или изменето оттогаш. Уредете го профилот.')->warning()->send();
                }
            });
    }

    /**
     * Name cleanup: merge a group of duplicate website drafts.
     */
    public static function mergeDuplicatesAction(): Action
    {
        return Action::make('mergeDuplicates')
            ->label('Спои ги')
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
                Notification::make()->title("Споени {$result['merged']}, прескокнати {$result['skipped']} (веќе не се скриен нацрт, или ги нема).")->success()->send();
            });
    }

    /**
     * Name cleanup: keep the current value (or keep duplicates apart).
     */
    public static function keepNameAction(): Action
    {
        return Action::make('keepName')
            ->label('Остави')
            ->icon('heroicon-o-hand-raised')
            ->color('gray')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record) && $record->source === NameCleanup::SOURCE)
            ->action(fn (ImportReviewItem $record) => self::open($record) ? app(NameReviewActions::class)->keep($record, self::actor()) : null);
    }

    public static function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label(fn (ImportReviewItem $record): string => $record->kind === ImportReviewKind::Changed ? 'Означено' : 'Отфрли')
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
            $subject instanceof Doctor => $subject->full_name.($subject->is_published ? ' (јавен)' : ' (скриен нацрт)'),
            default => $subject->name.($subject->is_published ? ' (јавен)' : ' (скриен нацрт)'),
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
     * legacy English titles from older runs are rewritten from details.
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

                return sprintf('Исто име · %d профили', $count > 0 ? $count : count($ids));
            }

            $suffix = $elsewhere > 0 ? sprintf(' · %d во друг град', $elsewhere) : '';

            return $facility !== ''
                ? sprintf('Можни дупликати · %d · %s%s', $count, $facility, $suffix)
                : sprintf('Можни дупликати · %d%s', $count, $suffix);
        }

        if (($details['reason'] ?? null) === NameCleanup::REASON && isset($details['changes']) && is_array($details['changes'])) {
            return sprintf('%d имиња · %s', count($details['changes']), NameCleanup::PROBLEMS[$details['problem'] ?? ''] ?? ($details['problem'] ?? 'cleanup'));
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
            ImportReviewKind::Conflict => sprintf('%s: „%s“ → „%s“', $details['field'] ?? '?', $details['current'] ?? '—', $details['incoming'] ?? '—'),
            ImportReviewKind::Changed => implode(', ', array_keys((array) ($details['fields'] ?? []))) ?: '—',
            ImportReviewKind::Missing => sprintf('Отсутен %s увози%s', $details['runs'] ?? '?', ($details['published'] ?? false) ? ', јавен' : ''),
            ImportReviewKind::Unmatched => self::reasonLabel((string) ($details['reason'] ?? 'unmatched')),
            ImportReviewKind::New => ($details['needs_licence_verification'] ?? false)
                ? 'Проверете лиценца пред објава'
                : (string) ($details['source_url'] ?? '—'),
            ImportReviewKind::Uncertain => self::uncertainSummary($details),
        };
    }

    /**
     * Full guidance for the View page (Macedonian for known shapes; stored text otherwise).
     */
    public static function guidance(ImportReviewItem $record): string
    {
        $details = $record->details ?? [];
        $structured = self::structuredGuidance($details);

        if ($structured !== null) {
            return $structured;
        }

        $action = trim((string) ($details['action'] ?? ''));

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
                'website_into_register' => 'Спои во ФЗОМ профил, или остави одвоено',
                'website_twins' => 'Спои веб-нацрти, или остави одвоено',
                'namesakes' => 'Споредете истоименици · спојте само рачно',
                default => 'Спои ги / Остави',
            };
        }

        if (($details['reason'] ?? null) === NameCleanup::REASON) {
            if (isset($details['suggestion']) && is_string($details['suggestion']) && $details['suggestion'] !== '') {
                return 'Прифати предлог, или остави';
            }

            if (isset($details['changes'])) {
                return 'Прифати ги предлозите, или остави';
            }

            return NameCleanup::PROBLEMS[$details['problem'] ?? ''] ?? 'Уредете го профилот ако треба';
        }

        if (($details['reason'] ?? null) === 'flagged_source') {
            return 'Довери ја страницата ако е тековна и чиста';
        }

        return self::reasonLabel((string) ($details['reason'] ?? $details['action'] ?? '—'));
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function structuredGuidance(array $details): ?string
    {
        if (($details['reason'] ?? null) === DuplicateFinder::REASON || isset($details['pairs'])) {
            $elsewhere = (int) ($details['other_town'] ?? 0);

            return match ((string) ($details['kind'] ?? '')) {
                'website_into_register' => 'Секој нацрт го носи името на точно еден ФЗОМ лекар во регистарот'
                    .($elsewhere > 0 ? " ({$elsewhere} се во друг град)" : '')
                    .'. „Спои ги“ го пренесува работното место (и титулата/специјалностите кога профилот нема) во ФЗОМ профилот и го брише нацртот; „Остави“ ги држи одвоени.',
                'website_twins' => 'Иста личност на две страници? „Спои ги“ го пренесува работното место во другиот профил (јавниот, или најстариот нацрт) и го брише нацртот; „Остави“ ги држи одвоени.',
                'namesakes' => 'Неколку профили со ова име (можни истоименици): споредете ги; спојте рачно само ако се иста личност, или „Остави“.',
                default => null,
            };
        }

        if (($details['reason'] ?? null) === NameCleanup::REASON) {
            $problem = NameCleanup::PROBLEMS[$details['problem'] ?? ''] ?? null;

            if ($problem === null) {
                return null;
            }

            if (isset($details['changes'])) {
                return $problem.' „Прифати предлог“ ги поставува сите предлози (профил изменет оттогаш се прескокнува); „Остави“ ги задржува сите.';
            }

            return isset($details['suggestion']) && is_string($details['suggestion']) && $details['suggestion'] !== ''
                ? $problem.' „Прифати предлог“ ја поставува предложената вредност.'
                : $problem.' Уредете го профилот ако треба промена.';
        }

        return null;
    }

    private static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'flagged_source' => 'Означен извор',
            'possible_duplicate' => 'Можен дупликат',
            'name_cleanup' => 'Чистење на имиња',
            'unmatched', '' => 'Неповрзано',
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
