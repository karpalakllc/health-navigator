<?php

namespace App\Filament\Resources\KomoraLicences;

use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\KomoraLicences\Pages\ListKomoraLicences;
use App\Models\KomoraLicence;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Лекарска комора licence list as last imported (read-only,
 * licences.manage): what matching did with each licence, which have expired
 * and which dropped off the list. Licences that need a decision are in the
 * import review queue; nothing here publishes or unpublishes a profile.
 */
class KomoraLicenceResource extends Resource
{
    protected static ?string $model = KomoraLicence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?string $navigationLabel = 'Licences (Комора)';

    protected static string|\UnitEnum|null $navigationGroup = 'Data import';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'licence';

    protected static ?string $pluralModelLabel = 'licences';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name (as published)')
                    ->searchable(['full_name', 'name_key'])
                    ->sortable(),
                TextColumn::make('specialty')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('valid_until')
                    ->label('Valid until')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn (KomoraLicence $record): ?string => $record->isExpired() ? 'danger' : null)
                    ->description(fn (KomoraLicence $record): ?string => $record->isExpired() ? 'Expired' : null),
                TextColumn::make('outcome')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => KomoraLicence::OUTCOMES[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'attached', 'unchanged' => 'success',
                        'ambiguous', 'specialty_mismatch', 'conflict', 'locked' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('doctor.full_name')
                    ->label('Doctor profile')
                    ->placeholder('—')
                    ->url(fn (KomoraLicence $record): ?string => $record->doctor_id !== null && auth()->user()?->can('doctors.update')
                        ? DoctorResource::getUrl('edit', ['record' => $record->doctor_id])
                        : null),
                TextColumn::make('missing_since')
                    ->label('Off the list since')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('licence_number')
                    ->label('Licence no. (internal)')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source_reference')
                    ->label('Where in the list')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('list_date')
                    ->label('List of')
                    ->date('d.m.Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('full_name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('doctor'))
            ->filters([
                SelectFilter::make('outcome')->options(KomoraLicence::OUTCOMES),
                Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('valid_until')->whereDate('valid_until', '<', now()->toDateString())),
                Filter::make('missing')
                    ->label('Off the list')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('missing_since')),
                Filter::make('attached_and_lapsed')
                    ->label('On a profile, expired or off the list')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('doctor_id')->where(
                        fn (Builder $inner): Builder => $inner->whereNotNull('missing_since')
                            ->orWhereDate('valid_until', '<', now()->toDateString()),
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKomoraLicences::route('/'),
        ];
    }
}
