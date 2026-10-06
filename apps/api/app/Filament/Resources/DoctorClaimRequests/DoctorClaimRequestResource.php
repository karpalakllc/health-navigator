<?php

namespace App\Filament\Resources\DoctorClaimRequests;

use App\Actions\DoctorAccount\AssignDoctorOwner;
use App\Actions\DoctorAccount\DoctorAccountException;
use App\Enums\DoctorClaimRequestStatus;
use App\Filament\Resources\DoctorClaimRequests\Pages\ListDoctorClaimRequests;
use App\Filament\Resources\DoctorClaimRequests\Pages\ViewDoctorClaimRequest;
use App\Models\DoctorClaimRequest;
use App\Models\User;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * „Ова е мој профил“ requests from members. Verify the person outside the
 * platform (call the workplace, check the Лекарска комора register), then
 * assign their account to the profile or reject the request. Nothing is
 * emailed automatically: contact the person with the details they left.
 */
class DoctorClaimRequestResource extends Resource
{
    protected static ?string $model = DoctorClaimRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Profile claims';

    protected static string|\UnitEnum|null $navigationGroup = 'Directory';

    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = 'profile claim';

    protected static ?string $pluralModelLabel = 'profile claims';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = DoctorClaimRequest::query()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('status')
                ->badge()
                ->formatStateUsing(fn (DoctorClaimRequestStatus $state): string => ucfirst($state->value)),
            TextEntry::make('doctor.full_name')
                ->label('Doctor profile'),
            TextEntry::make('doctor_owner')
                ->label('Profile currently managed by')
                ->state(fn (DoctorClaimRequest $record): string => $record->doctor?->owner->email ?? '—'),
            TextEntry::make('user.email')
                ->label('Requested by (account)'),
            TextEntry::make('contact')
                ->label('Contact for verification')
                ->placeholder('—'),
            TextEntry::make('message')
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('created_at')
                ->dateTime(),
            TextEntry::make('resolvedBy.name')
                ->label('Resolved by')
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
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (DoctorClaimRequestStatus $state): string => ucfirst($state->value))
                    ->color(fn (DoctorClaimRequestStatus $state): string => match ($state) {
                        DoctorClaimRequestStatus::Pending => 'warning',
                        DoctorClaimRequestStatus::Approved => 'success',
                        DoctorClaimRequestStatus::Rejected => 'gray',
                    }),
                TextColumn::make('doctor.full_name')
                    ->label('Doctor')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Account')
                    ->searchable(),
                TextColumn::make('contact')
                    ->limit(40),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->modifyQueryUsing(fn ($query) => $query->with(['doctor.owner', 'user']))
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(DoctorClaimRequestStatus::cases())->mapWithKeys(
                        fn (DoctorClaimRequestStatus $status) => [$status->value => ucfirst($status->value)],
                    )->all())
                    ->default(DoctorClaimRequestStatus::Pending->value),
            ])
            ->recordActions([
                ViewAction::make(),
                self::assignAction(),
                self::rejectAction(),
            ]);
    }

    public static function assignAction(): Action
    {
        return Action::make('assign')
            ->label('Assign account')
            ->color('success')
            ->icon('heroicon-o-user-plus')
            ->visible(fn (DoctorClaimRequest $record): bool => self::canResolve($record))
            ->requiresConfirmation()
            ->modalDescription('Only after verifying the person outside the platform. Their account will manage this doctor profile.')
            ->action(function (DoctorClaimRequest $record): void {
                if (! self::canResolve($record) || $record->doctor === null) {
                    return;
                }

                try {
                    app(AssignDoctorOwner::class)->handle($record->doctor, $record->user, self::actor(), $record);
                } catch (DoctorAccountException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Account assigned')->success()->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->color('danger')
            ->icon('heroicon-o-x-mark')
            ->visible(fn (DoctorClaimRequest $record): bool => self::canResolve($record))
            ->schema([
                Textarea::make('resolution_note')
                    ->label('Reason (staff only)')
                    ->required()
                    ->minLength(5)
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (DoctorClaimRequest $record, array $data): void {
                if (! self::canResolve($record)) {
                    return;
                }

                $record->forceFill([
                    'status' => DoctorClaimRequestStatus::Rejected,
                    'resolved_by_id' => self::actor()->getKey(),
                    'resolved_at' => now(),
                    'resolution_note' => trim((string) ($data['resolution_note'] ?? '')),
                ])->save();

                Notification::make()->title('Claim rejected')->success()->send();
            });
    }

    private static function canResolve(DoctorClaimRequest $record): bool
    {
        return $record->status === DoctorClaimRequestStatus::Pending
            && (auth()->user()?->can('update', $record) ?? false);
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
            'index' => ListDoctorClaimRequests::route('/'),
            'view' => ViewDoctorClaimRequest::route('/{record}'),
        ];
    }
}
