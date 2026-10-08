<?php

namespace App\Filament\Resources\LicenceSpecialtyMappings;

use App\Filament\Resources\LicenceSpecialtyMappings\Pages\CreateLicenceSpecialtyMapping;
use App\Filament\Resources\LicenceSpecialtyMappings\Pages\EditLicenceSpecialtyMapping;
use App\Filament\Resources\LicenceSpecialtyMappings\Pages\ListLicenceSpecialtyMappings;
use App\Models\LicenceSpecialtyMapping;
use App\Support\Licences\SpecialtyKey;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
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
 * How the Лекарска комора's and ФЗОМ's specialty wording groups together,
 * for matching licences to imported doctors (licences.manage). A licence
 * fits a doctor when its group, or one of its compatible groups, is among
 * the doctor's. Wording first seen in a new list appears here unmapped;
 * licences with it go to the review queue until it is mapped.
 */
class LicenceSpecialtyMappingResource extends Resource
{
    protected static ?string $model = LicenceSpecialtyMapping::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Licence specialty mapping';

    protected static string|\UnitEnum|null $navigationGroup = 'Data import';

    protected static ?int $navigationSort = 41;

    protected static ?string $modelLabel = 'specialty mapping';

    protected static ?string $pluralModelLabel = 'specialty mappings';

    protected static ?string $recordTitleAttribute = 'source_text';

    public static function getNavigationBadge(): ?string
    {
        $unmapped = LicenceSpecialtyMapping::query()->unmapped()->count();

        return $unmapped > 0 ? (string) $unmapped : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('source')
                ->options(LicenceSpecialtyMapping::SOURCES)
                ->required()
                ->default(LicenceSpecialtyMapping::SOURCE_KOMORA)
                ->disabledOn('edit'),
            TextInput::make('source_text')
                ->label('Wording')
                ->required()
                ->maxLength(255)
                ->disabledOn('edit')
                ->helperText('Exactly as the source writes it. Case, Latin look-alike letters and punctuation do not matter.')
                ->rule(fn (Get $get, ?LicenceSpecialtyMapping $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                    if ($record !== null || ! is_string($value)) {
                        return;
                    }

                    $exists = LicenceSpecialtyMapping::query()
                        ->where('source', $get('source'))
                        ->where('source_key', SpecialtyKey::for($value))
                        ->exists();

                    if ($exists) {
                        $fail('This wording is already in the mapping.');
                    }
                }),
            Toggle::make('is_ignored')
                ->label('Not a physician\'s specialty')
                ->live()
                ->helperText('Pharmacists, dentists, psychologists and other professions. Never matched to a licence.'),
            TextInput::make('group_key')
                ->label('Group')
                ->maxLength(64)
                ->datalist(fn (): array => LicenceSpecialtyMapping::knownGroups())
                ->requiredUnless('is_ignored', true)
                ->hidden(fn (Get $get): bool => (bool) $get('is_ignored'))
                ->helperText('What the specialty is, e.g. „kardiologija“. Wording of either source with the same group means the same specialty.'),
            TagsInput::make('compatible_groups')
                ->label('Also fits')
                ->suggestions(fn (): array => LicenceSpecialtyMapping::knownGroups())
                ->hidden(fn (Get $get): bool => (bool) $get('is_ignored'))
                ->helperText('Komora wording only: groups a doctor with this licence may be listed under at work (a cardiologist contracted as an internist: „interna-medicina“).'),
            Select::make('specialty_id')
                ->label('Our specialty')
                ->relationship('specialty', 'name')
                ->searchable()
                ->preload()
                ->hidden(fn (Get $get): bool => (bool) $get('is_ignored'))
                ->helperText('Optional: the directory specialty this group is.'),
            Textarea::make('notes')
                ->maxLength(1000)
                ->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => LicenceSpecialtyMapping::SOURCES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('source_text')
                    ->label('Wording')
                    ->searchable(['source_text', 'source_key'])
                    ->sortable()
                    ->wrap(),
                TextColumn::make('group_key')
                    ->label('Group')
                    ->placeholder('Unmapped')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('compatible_groups')
                    ->label('Also fits')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('specialty.name')
                    ->label('Our specialty')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_ignored')
                    ->label('Not a physician')
                    ->boolean(),
                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime()
                    ->placeholder('Not reviewed')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('source_text')
            ->filters([
                SelectFilter::make('source')->options(LicenceSpecialtyMapping::SOURCES),
                Filter::make('unmapped')
                    ->label('Unmapped')
                    ->query(fn (Builder $query): Builder => $query->whereNull('group_key')->where('is_ignored', false)),
                TernaryFilter::make('is_ignored')->label('Not a physician'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLicenceSpecialtyMappings::route('/'),
            'create' => CreateLicenceSpecialtyMapping::route('/create'),
            'edit' => EditLicenceSpecialtyMapping::route('/{record}/edit'),
        ];
    }
}
