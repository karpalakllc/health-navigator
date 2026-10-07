<?php

namespace App\Filament\Resources\ImportReviewItems;

use App\Enums\ImportReviewKind;
use App\Enums\ImportReviewStatus;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Filament\Resources\ImportReviewItems\Pages\ListImportReviewItems;
use App\Filament\Resources\ImportReviewItems\Pages\ViewImportReviewItem;
use App\Models\Doctor;
use App\Models\ImportReviewItem;
use App\Models\User;
use App\Support\Import\ImportReviewActions;
use App\Support\Verification\Engine\EvidenceLoader;
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
use Illuminate\Database\Eloquent\Collection;

/**
 * The import review queue („Увоз на податоци“): drafts to publish, values
 * that changed on live profiles, conflicts with staff edits, records the
 * source dropped, and rows the importer could not place.
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
            TextEntry::make('kind')->badge()->formatStateUsing(fn (ImportReviewKind $state): string => $state->label()),
            TextEntry::make('status')->badge()->formatStateUsing(fn (ImportReviewStatus $state): string => ucfirst($state->value)),
            TextEntry::make('source'),
            TextEntry::make('title')->columnSpanFull(),
            TextEntry::make('subject_link')
                ->label('Profile')
                ->state(fn (ImportReviewItem $record): string => self::subjectLabel($record))
                ->url(fn (ImportReviewItem $record): ?string => self::subjectUrl($record)),
            TextEntry::make('run.id')->label('Import run')->placeholder('—'),
            KeyValueEntry::make('details')
                ->state(fn (ImportReviewItem $record): array => self::flatDetails($record->details ?? []))
                ->columnSpanFull(),
            TextEntry::make('resolution')->placeholder('—'),
            TextEntry::make('resolvedBy.name')->label('Resolved by')->placeholder('—'),
            TextEntry::make('resolved_at')->dateTime()->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn (ImportReviewKind $state): string => $state->label())
                    ->color(fn (ImportReviewKind $state): string => match ($state) {
                        ImportReviewKind::New => 'info',
                        ImportReviewKind::Changed => 'gray',
                        ImportReviewKind::Conflict, ImportReviewKind::Missing => 'warning',
                        ImportReviewKind::Unmatched => 'danger',
                        ImportReviewKind::Uncertain => 'primary',
                    }),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('summary')
                    ->label('Details')
                    ->state(fn (ImportReviewItem $record): string => self::summary($record))
                    ->wrap(),
                TextColumn::make('source')->badge()->color('gray'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (ImportReviewStatus $state): string => ucfirst($state->value))->toggleable(),
                TextColumn::make('priority')->numeric()->sortable()->toggleable(),
                TextColumn::make('created_at')->label('Raised')->dateTime()->sortable(),
            ])
            // What decides the most first (the verification engine ranks by
            // impact: published profiles, many doctors behind one decision).
            ->defaultSort(fn ($query) => $query->orderByDesc('priority')->orderByDesc('created_at'))
            ->modifyQueryUsing(fn ($query) => $query->with('run'))
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ImportReviewStatus::cases())->mapWithKeys(fn (ImportReviewStatus $s) => [$s->value => ucfirst($s->value)])->all())
                    ->default(ImportReviewStatus::Open->value),
                SelectFilter::make('source')
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
                self::dismissAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Publish selected drafts')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Publishes the doctor and facility profiles of the selected "new" items. Check each one first: imported drafts are not verified.')
                        ->visible(fn (): bool => self::canManage())
                        ->action(function (Collection $records): void {
                            $published = 0;

                            foreach ($records as $record) {
                                if ($record instanceof ImportReviewItem && $record->kind === ImportReviewKind::New
                                    && $record->status === ImportReviewStatus::Open
                                    && app(ImportReviewActions::class)->publish($record, self::actor())) {
                                    $published++;
                                }
                            }

                            Notification::make()->title("Published {$published} profiles")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('dismiss')
                        ->label('Dismiss selected')
                        ->icon('heroicon-o-x-mark')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => self::canManage())
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                if ($record instanceof ImportReviewItem && $record->status === ImportReviewStatus::Open) {
                                    app(ImportReviewActions::class)->dismiss($record, self::actor());
                                }
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
            ->modalDescription('The profile becomes public. Imported drafts are not verified: check the name, workplace and specialty first.')
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
            ->modalDescription(fn (ImportReviewItem $record): string => sprintf('Replace "%s" with "%s".', $record->details['current'] ?? '—', $record->details['incoming'] ?? '—'))
            ->action(function (ImportReviewItem $record): void {
                if (self::open($record) && ! app(ImportReviewActions::class)->acceptIncoming($record, self::actor())) {
                    Notification::make()->title('Not applied: the field is locked, the profile changed since, or this field cannot be set from the review queue. Edit the profile.')->warning()->send();
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
            ->modalDescription('The current value stays and the field is locked against future imports (unlock it on the profile).')
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
            ->modalDescription('Unpublishes the profile. Nothing is deleted; reviews are kept.')
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
            ->modalDescription('Its staff list counts as verification evidence again, for every doctor it lists. Do this only after checking that the site is current and clean.')
            ->action(function (ImportReviewItem $record): void {
                if (self::open($record) && $record->resolve(ImportReviewStatus::Resolved, EvidenceLoader::TRUSTED_RESOLUTION, self::actor())) {
                    Notification::make()->title('Trusted. The next verification run (nightly, or import:adjudicate) uses it.')->success()->send();
                }
            });
    }

    public static function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label(fn (ImportReviewItem $record): string => $record->kind === ImportReviewKind::Changed ? 'Mark seen' : 'Dismiss')
            ->icon('heroicon-o-check')
            ->color('gray')
            ->visible(fn (ImportReviewItem $record): bool => self::open($record))
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

    private static function summary(ImportReviewItem $record): string
    {
        $details = $record->details ?? [];

        return match ($record->kind) {
            ImportReviewKind::Conflict => sprintf('%s: "%s" → "%s"', $details['field'] ?? '?', $details['current'] ?? '—', $details['incoming'] ?? '—'),
            ImportReviewKind::Changed => implode(', ', array_keys((array) ($details['fields'] ?? []))),
            ImportReviewKind::Missing => 'Absent '.($details['runs'] ?? '?').' runs'.(($details['published'] ?? false) ? ', public' : ''),
            ImportReviewKind::Unmatched => str_replace('_', ' ', (string) ($details['reason'] ?? 'unmatched')),
            ImportReviewKind::New => ($details['needs_licence_verification'] ?? false) ? 'Verify licence before publishing' : (string) ($details['source_url'] ?? ''),
            ImportReviewKind::Uncertain => (string) ($details['action'] ?? str_replace('_', ' ', (string) ($details['reason'] ?? ''))),
        };
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
                $flat[$name] = is_array($value) ? implode(', ', array_map('strval', $value)) : (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value);
            }
        }

        return $flat;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportReviewItems::route('/'),
            'view' => ViewImportReviewItem::route('/{record}'),
        ];
    }
}
