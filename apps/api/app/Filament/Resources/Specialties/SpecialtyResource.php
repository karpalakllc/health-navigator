<?php

namespace App\Filament\Resources\Specialties;

use App\Filament\Resources\Specialties\Pages\CreateSpecialty;
use App\Filament\Resources\Specialties\Pages\EditSpecialty;
use App\Filament\Resources\Specialties\Pages\ListSpecialties;
use App\Filament\Resources\Specialties\Schemas\SpecialtyForm;
use App\Filament\Resources\Specialties\Tables\SpecialtiesTable;
use App\Models\Specialty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SpecialtyResource extends Resource
{
    protected static ?string $model = Specialty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Specialties';

    protected static string|\UnitEnum|null $navigationGroup = 'Directory';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'specialty';

    protected static ?string $pluralModelLabel = 'specialties';

    public static function form(Schema $schema): Schema
    {
        return SpecialtyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpecialtiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpecialties::route('/'),
            'create' => CreateSpecialty::route('/create'),
            'edit' => EditSpecialty::route('/{record}/edit'),
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
