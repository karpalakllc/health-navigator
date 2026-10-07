<?php

namespace App\Filament\Resources\ProfileCorrections;

use App\Enums\ProfileCorrectionField;
use App\Enums\ProfileCorrectionStatus;
use App\Enums\ProfileCorrectionType;
use App\Enums\ProfileReportReason;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Filament\Resources\ProfileCorrections\Pages\ListProfileCorrections;
use App\Filament\Resources\ProfileCorrections\Pages\ViewProfileCorrection;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\ProfileCorrection;
use App\Models\User;
use App\Support\PublicWebUrl;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * „Пријави грешка во профилот“ and objection / removal requests from the
 * public (docs/data-import.md, research memo §2.1). Fix the profile on its
 * own edit page, then close the request here with a note. Corrections are due
 * 15 days after receipt, objections 30; the due date is fixed at receipt.
 * Nothing is emailed to the requester automatically: reply to the contact
 * they left (an objection refused needs the reasons and the right to
 * complain to АЗЛП or go to court).
 *
 * Profile reports („Пријави профил“, W7-C) share the queue. A profile with
 * several independent open reports is sorted to the top with a red count;
 * one decision closes every open report on that profile. Nothing is hidden
 * automatically: unpublish or fix the profile on its edit page.
 */
class ProfileCorrectionResource extends Resource
{
    protected static ?string $model = ProfileCorrection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Corrections';

    protected static string|\UnitEnum|null $navigationGroup = 'Directory';

    protected static ?int $navigationSort = 13;

    protected static ?string $modelLabel = 'correction request';

    protected static ?string $pluralModelLabel = 'correction requests';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = ProfileCorrection::query()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return ProfileCorrection::query()->overdue()->exists()
            || ProfileCorrection::query()->open()->onPriorityProfiles()->exists()
            ? 'danger'
            : 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('type')
                ->badge()
                ->formatStateUsing(fn (ProfileCorrectionType $state): string => $state->label()),
            TextEntry::make('status')
                ->badge()
                ->formatStateUsing(fn (ProfileCorrectionStatus $state): string => $state->label())
                ->color(fn (ProfileCorrectionStatus $state): string => self::statusColor($state)),
            TextEntry::make('subject_name')
                ->label('Profile')
                ->state(fn (ProfileCorrection $record): string => ucfirst($record->subjectKind()).': '.$record->subjectName())
                ->url(fn (ProfileCorrection $record): ?string => self::profileEditUrl($record)),
            TextEntry::make('public_url')
                ->label('Public page')
                ->state(fn (ProfileCorrection $record): ?string => self::publicUrl($record))
                ->url(fn (ProfileCorrection $record): ?string => self::publicUrl($record), shouldOpenInNewTab: true)
                ->placeholder('—'),
            TextEntry::make('field')
                ->label('Part of the profile')
                ->formatStateUsing(fn (?ProfileCorrectionField $state): string => $state?->label() ?? '—')
                ->placeholder('—')
                ->hidden(fn (ProfileCorrection $record): bool => $record->type === ProfileCorrectionType::Report),
            TextEntry::make('report_reason')
                ->label('Reason')
                ->formatStateUsing(fn (?ProfileReportReason $state): string => $state?->label() ?? '—')
                ->visible(fn (ProfileCorrection $record): bool => $record->type === ProfileCorrectionType::Report),
            TextEntry::make('open_reports')
                ->label('Open reports on this profile')
                ->state(fn (ProfileCorrection $record): int => $record->openReportCount())
                ->badge()
                ->color(fn (ProfileCorrection $record): string => $record->isPriority() ? 'danger' : 'gray')
                ->helperText('Independent reporters: each account and each guest address once.')
                ->visible(fn (ProfileCorrection $record): bool => $record->type === ProfileCorrectionType::Report),
            TextEntry::make('due_at')
                ->label('Answer due')
                ->date()
                ->color(fn (ProfileCorrection $record): ?string => $record->isOverdue() ? 'danger' : null),
            TextEntry::make('message')
                ->label(fn (ProfileCorrection $record): string => $record->type === ProfileCorrectionType::Report ? 'Note (optional)' : 'Message')
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('contact')
                ->hidden(fn (ProfileCorrection $record): bool => $record->type === ProfileCorrectionType::Report)
                ->label(fn (ProfileCorrection $record): string => $record->type === ProfileCorrectionType::Objection
                    ? 'Contact for verification'
                    : 'Contact e-mail (optional)')
                ->placeholder('—'),
            TextEntry::make('user.email')
                ->label('Sent from account')
                ->placeholder('Not signed in'),
            TextEntry::make('created_at')
                ->label('Received')
                ->dateTime(),
            TextEntry::make('resolvedBy.name')
                ->label('Closed by')
                ->placeholder('—'),
            TextEntry::make('resolved_at')
                ->label('Closed')
                ->dateTime()
                ->placeholder('—'),
            TextEntry::make('resolution_note')
                ->label('Staff note')
                ->placeholder('—')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (ProfileCorrectionType $state): string => $state->label())
                    ->color(fn (ProfileCorrectionType $state): string => match ($state) {
                        ProfileCorrectionType::Objection => 'danger',
                        ProfileCorrectionType::Report => 'warning',
                        ProfileCorrectionType::Correction => 'info',
                    }),
                TextColumn::make('subject_name')
                    ->label('Profile')
                    ->state(fn (ProfileCorrection $record): string => $record->subjectName())
                    ->description(fn (ProfileCorrection $record): string => ucfirst($record->subjectKind())),
                TextColumn::make('field')
                    ->label('Part / reason')
                    ->state(fn (ProfileCorrection $record): ?string => $record->type === ProfileCorrectionType::Report
                        ? $record->report_reason?->label()
                        : $record->field?->label())
                    ->placeholder('—'),
                TextColumn::make('open_report_count')
                    ->label('Reports')
                    ->badge()
                    ->state(fn (ProfileCorrection $record): ?int => $record->type === ProfileCorrectionType::Report && $record->isOpen()
                        ? $record->openReportCount()
                        : null)
                    ->color(fn (ProfileCorrection $record): string => $record->isPriority() ? 'danger' : 'gray')
                    ->tooltip('Independent open reports on this profile')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProfileCorrectionStatus $state): string => $state->label())
                    ->color(fn (ProfileCorrectionStatus $state): string => self::statusColor($state)),
                TextColumn::make('due_at')
                    ->label('Due')
                    ->date()
                    ->sortable()
                    ->color(fn (ProfileCorrection $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (ProfileCorrection $record): ?string => $record->isOverdue() ? 'Overdue' : null),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('due_at', 'asc')
            // Profiles with several independent open reports first; then the
            // chosen sort (answer due date by default).
            ->modifyQueryUsing(fn ($query) => $query->with(['subject', 'user'])->withOpenReportCount()->prioritised())
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ProfileCorrectionStatus::cases())->mapWithKeys(
                        fn (ProfileCorrectionStatus $status) => [$status->value => $status->label()],
                    )->all())
                    ->default(ProfileCorrectionStatus::Open->value),
                SelectFilter::make('type')
                    ->options(collect(ProfileCorrectionType::cases())->mapWithKeys(
                        fn (ProfileCorrectionType $type) => [$type->value => $type->label()],
                    )->all()),
                Filter::make('priority')
                    ->label('Often reported profiles only')
                    ->query(fn ($query) => $query->onPriorityProfiles()),
            ])
            ->recordActions([
                ViewAction::make(),
                self::resolveAction(),
                self::declineAction(),
            ]);
    }

    public static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->label(fn (ProfileCorrection $record): string => match ($record->type) {
                ProfileCorrectionType::Objection => 'Uphold (profile removed)',
                ProfileCorrectionType::Report => 'Acted on',
                ProfileCorrectionType::Correction => 'Mark corrected',
            })
            ->color('success')
            ->icon('heroicon-o-check')
            ->visible(fn (ProfileCorrection $record): bool => self::canClose($record))
            ->modalDescription(fn (ProfileCorrection $record): string => match ($record->type) {
                ProfileCorrectionType::Objection => 'Upholding unpublishes the doctor profile now and stops every import from adding or publishing the person again (Data import → Suppressed profiles). Delete the profile on its edit page as well if it should go entirely. The note is staff-only; tell the person by the contact they left.',
                ProfileCorrectionType::Report => self::reportCloseHint($record, 'Fix, unpublish or delete the profile on its edit page first; nothing changes on the profile from here.'),
                ProfileCorrectionType::Correction => 'Correct the profile on its edit page first. The note is staff-only.',
            })
            ->schema([self::noteField('What was changed')])
            ->action(fn (ProfileCorrection $record, array $data) => self::close($record, ProfileCorrectionStatus::Resolved, $data));
    }

    public static function declineAction(): Action
    {
        return Action::make('decline')
            ->label(fn (ProfileCorrection $record): string => $record->type === ProfileCorrectionType::Objection
                ? 'Refuse (profile kept)'
                : 'No change')
            ->color('gray')
            ->icon('heroicon-o-x-mark')
            ->visible(fn (ProfileCorrection $record): bool => self::canClose($record))
            ->modalDescription(fn (ProfileCorrection $record): string => match ($record->type) {
                ProfileCorrectionType::Objection => 'Record the balancing test: why the public interest in a complete directory prevails here. Reply to the person with these reasons and their right to complain to АЗЛП or go to court (ЗЗЛП чл. 16(4)).',
                ProfileCorrectionType::Report => self::reportCloseHint($record, 'Say why the profile stays as it is.'),
                ProfileCorrectionType::Correction => 'Say why nothing changes (already correct, not verifiable, not about this profile).',
            })
            ->schema([self::noteField('Reasons')])
            ->action(fn (ProfileCorrection $record, array $data) => self::close($record, ProfileCorrectionStatus::Declined, $data));
    }

    /** A report's decision covers every open report on the profile: say so. */
    private static function reportCloseHint(ProfileCorrection $record, string $lead): string
    {
        $open = $record->openReportCount();

        return $open > 1
            ? $lead." This closes all {$open} open reports on this profile. The note is staff-only."
            : $lead.' The note is staff-only.';
    }

    private static function noteField(string $label): Textarea
    {
        return Textarea::make('resolution_note')
            ->label($label.' (staff only)')
            ->required()
            ->minLength(5)
            ->maxLength(ProfileCorrection::NOTE_MAX_LENGTH)
            ->rows(4);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function close(ProfileCorrection $record, ProfileCorrectionStatus $outcome, array $data): void
    {
        if (! self::canClose($record)) {
            return;
        }

        $closed = $record->close($outcome, self::actor(), trim((string) ($data['resolution_note'] ?? '')));

        $closed
            ? Notification::make()->title('Request closed')->success()->send()
            : Notification::make()->title('Someone else closed this request first')->warning()->send();
    }

    private static function canClose(ProfileCorrection $record): bool
    {
        return $record->isOpen() && (auth()->user()?->can('update', $record) ?? false);
    }

    private static function statusColor(ProfileCorrectionStatus $state): string
    {
        return match ($state) {
            ProfileCorrectionStatus::Open => 'warning',
            ProfileCorrectionStatus::Resolved => 'success',
            ProfileCorrectionStatus::Declined => 'gray',
        };
    }

    /**
     * The profile's edit page, for staff who may edit it and while it exists.
     */
    public static function profileEditUrl(ProfileCorrection $record): ?string
    {
        $subject = $record->subject;

        if (! $subject instanceof Model || self::isTrashed($subject) || ! (auth()->user()?->can('update', $subject) ?? false)) {
            return null;
        }

        return match (true) {
            $subject instanceof Doctor => DoctorResource::getUrl('edit', ['record' => $subject]),
            $subject instanceof Facility => FacilityResource::getUrl('edit', ['record' => $subject]),
            default => null,
        };
    }

    private static function publicUrl(ProfileCorrection $record): ?string
    {
        $subject = $record->subject;

        if (! $subject instanceof Model || self::isTrashed($subject)) {
            return null;
        }

        return PublicWebUrl::forRecord($subject);
    }

    private static function isTrashed(Model $model): bool
    {
        return method_exists($model, 'trashed') && $model->trashed();
    }

    private static function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfileCorrections::route('/'),
            'view' => ViewProfileCorrection::route('/{record}'),
        ];
    }
}
