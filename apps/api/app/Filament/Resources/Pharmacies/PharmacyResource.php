<?php

namespace App\Filament\Resources\Pharmacies;

use App\Filament\Resources\Facilities\RelationManagers\ProductsRelationManager;
use App\Filament\Resources\Pharmacies\Pages\CreatePharmacy;
use App\Filament\Resources\Pharmacies\Pages\EditPharmacy;
use App\Filament\Resources\Pharmacies\Pages\ListPharmacies;
use App\Filament\Resources\Pharmacies\Schemas\PharmacyForm;
use App\Filament\Resources\Pharmacies\Tables\PharmaciesTable;
use App\Models\Facility;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PharmacyResource extends Resource
{
    protected static ?string $model = Facility::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Pharmacies';

    protected static string|\UnitEnum|null $navigationGroup = 'Directory';

    protected static ?int $navigationSort = 25;

    protected static ?string $modelLabel = 'pharmacy';

    protected static ?string $pluralModelLabel = 'pharmacies';

    public static function form(Schema $schema): Schema
    {
        return PharmacyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PharmaciesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPharmacies::route('/'),
            'create' => CreatePharmacy::route('/create'),
            'edit' => EditPharmacy::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('pharmacies.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('pharmacies.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('pharmacies.update') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('pharmacies.delete') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->pharmacy()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }
}
