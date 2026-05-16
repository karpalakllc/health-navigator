<?php

namespace App\Filament\Resources\ClinicalInterests;

use App\Filament\Resources\ClinicalInterests\Pages\CreateClinicalInterest;
use App\Filament\Resources\ClinicalInterests\Pages\EditClinicalInterest;
use App\Filament\Resources\ClinicalInterests\Pages\ListClinicalInterests;
use App\Filament\Resources\ClinicalInterests\Schemas\ClinicalInterestForm;
use App\Filament\Resources\ClinicalInterests\Tables\ClinicalInterestsTable;
use App\Models\ClinicalInterest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClinicalInterestResource extends Resource
{
    protected static ?string $model = ClinicalInterest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'Clinical interests';

    protected static string|\UnitEnum|null $navigationGroup = 'Directory';

    protected static ?int $navigationSort = 32;

    protected static ?string $modelLabel = 'clinical interest';

    protected static ?string $pluralModelLabel = 'clinical interests';

    public static function form(Schema $schema): Schema
    {
        return ClinicalInterestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClinicalInterestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClinicalInterests::route('/'),
            'create' => CreateClinicalInterest::route('/create'),
            'edit' => EditClinicalInterest::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
