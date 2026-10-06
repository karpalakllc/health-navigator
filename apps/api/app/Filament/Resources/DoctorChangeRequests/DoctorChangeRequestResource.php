<?php

namespace App\Filament\Resources\DoctorChangeRequests;

use App\Enums\DoctorChangeRequestStatus;
use App\Filament\Resources\DoctorChangeRequests\Pages\ListDoctorChangeRequests;
use App\Filament\Resources\DoctorChangeRequests\Pages\ViewDoctorChangeRequest;
use App\Filament\Support\DoctorChangeRequestActions;
use App\Models\DoctorChangeRequest;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Linked doctors' requests to change the sensitive fields of their profile
 * (name, title, qualifications, specialties, workplaces). The profile keeps
 * its values until a request here is approved.
 */
class DoctorChangeRequestResource extends Resource
{
    protected static ?string $model = DoctorChangeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Doctor change requests';

    protected static string|\UnitEnum|null $navigationGroup = 'Directory';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'change request';

    protected static ?string $pluralModelLabel = 'doctor change requests';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = DoctorChangeRequest::query()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('status')
                ->badge()
                ->formatStateUsing(fn (DoctorChangeRequestStatus $state): string => ucfirst($state->value)),
            TextEntry::make('doctor.full_name')
                ->label('Doctor profile'),
            TextEntry::make('user.email')
                ->label('Requested by (linked account)')
                ->placeholder('—'),
            TextEntry::make('created_at')
                ->dateTime(),
            TextEntry::make('summary')
                ->label('Requested changes (current → requested)')
                ->state(fn (DoctorChangeRequest $record): array => DoctorChangeRequestActions::summary($record))
                ->listWithLineBreaks()
                ->bulleted()
                ->columnSpanFull(),
            TextEntry::make('message')
                ->label('Note from the doctor')
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('reviewedBy.name')
                ->label('Decided by')
                ->placeholder('—'),
            TextEntry::make('reviewed_at')
                ->dateTime()
                ->placeholder('—'),
            TextEntry::make('rejection_reason')
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
                    ->formatStateUsing(fn (DoctorChangeRequestStatus $state): string => ucfirst($state->value))
                    ->color(fn (DoctorChangeRequestStatus $state): string => match ($state) {
                        DoctorChangeRequestStatus::Pending => 'warning',
                        DoctorChangeRequestStatus::Approved => 'success',
                        DoctorChangeRequestStatus::Rejected => 'danger',
                        DoctorChangeRequestStatus::Withdrawn => 'gray',
                    }),
                TextColumn::make('doctor.full_name')
                    ->label('Doctor')
                    ->searchable(),
                TextColumn::make('summary')
                    ->label('Requested changes')
                    ->state(fn (DoctorChangeRequest $record): array => DoctorChangeRequestActions::summary($record))
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->modifyQueryUsing(fn ($query) => $query->with(['doctor', 'user']))
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(DoctorChangeRequestStatus::cases())->mapWithKeys(
                        fn (DoctorChangeRequestStatus $status) => [$status->value => ucfirst($status->value)],
                    )->all())
                    ->default(DoctorChangeRequestStatus::Pending->value),
            ])
            ->recordActions([
                ViewAction::make(),
                DoctorChangeRequestActions::approve(),
                DoctorChangeRequestActions::reject(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDoctorChangeRequests::route('/'),
            'view' => ViewDoctorChangeRequest::route('/{record}'),
        ];
    }
}
