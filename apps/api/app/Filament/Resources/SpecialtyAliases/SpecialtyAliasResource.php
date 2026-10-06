<?php

namespace App\Filament\Resources\SpecialtyAliases;

use App\Filament\Resources\SpecialtyAliases\Pages\EditSpecialtyAlias;
use App\Filament\Resources\SpecialtyAliases\Pages\ListSpecialtyAliases;
use App\Models\SpecialtyAlias;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Specialty aliases: how a source's specialty wording (ФЗОМ, institution
 * websites) becomes one of our specialties. The importers add a wording the
 * first time they see it, with the catalogue default; from then on the
 * alias decides, so a correction here sticks. Doctors whose source record
 * uses the wording are re-linked on the next run (the import replaces the
 * specialty links it made itself, never staff-made ones).
 *
 * Not the licence specialty mapping (licences.manage), which only decides
 * whether a Комора licence fits a doctor — see docs/data-import.md §7.
 */
class SpecialtyAliasResource extends Resource
{
    protected static ?string $model = SpecialtyAlias::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $navigationLabel = 'Specialty aliases';

    protected static string|\UnitEnum|null $navigationGroup = 'Data import';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'specialty alias';

    protected static ?string $pluralModelLabel = 'specialty aliases';

    protected static ?string $recordTitleAttribute = 'raw';

    /** @var array<string, string> */
    public const SOURCES = [
        'fzom' => 'ФЗОМ',
        'website' => 'Website',
    ];

    public static function getNavigationBadge(): ?string
    {
        $unmapped = self::unmapped(SpecialtyAlias::query())->count();

        return $unmapped > 0 ? (string) $unmapped : null;
    }

    /**
     * @param  Builder<SpecialtyAlias>  $query
     * @return Builder<SpecialtyAlias>
     */
    private static function unmapped(Builder $query): Builder
    {
        return $query->whereNull('specialty_id')->where('is_excluded', false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('source')
                ->formatStateUsing(fn (?string $state): string => self::SOURCES[$state] ?? (string) $state)
                ->disabled()
                ->dehydrated(false),
            TextInput::make('raw')
                ->label('Wording')
                ->disabled()
                ->dehydrated(false)
                ->helperText('As the source first wrote it.'),
            Toggle::make('is_excluded')
                ->label('Not a profession we list')
                ->live()
                ->helperText('Pharmacist, psychologist, speech therapist… A person whose every specialty is excluded is not imported.'),
            Select::make('specialty_id')
                ->label('Our specialty')
                ->relationship('specialty', 'name')
                ->searchable()
                ->preload()
                ->hidden(fn (Get $get): bool => (bool) $get('is_excluded'))
                ->helperText('Hidden (imported) specialties are listed too. Empty = unmapped: the import reports the wording in the review queue.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::SOURCES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('raw')
                    ->label('Wording')
                    ->searchable(['raw', 'raw_key'])
                    ->sortable()
                    ->wrap(),
                TextColumn::make('specialty.name')
                    ->label('Our specialty')
                    ->placeholder('Unmapped')
                    ->searchable(),
                IconColumn::make('is_excluded')
                    ->label('Excluded')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Changed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('raw')
            ->filters([
                SelectFilter::make('source')->options(self::SOURCES),
                Filter::make('unmapped')
                    ->label('Unmapped')
                    ->query(fn (Builder $query): Builder => self::unmapped($query)),
                TernaryFilter::make('is_excluded')->label('Excluded'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpecialtyAliases::route('/'),
            'edit' => EditSpecialtyAlias::route('/{record}/edit'),
        ];
    }
}
