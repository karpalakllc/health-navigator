<?php

namespace App\Filament\Resources\Pharmacies;

use App\Filament\Resources\Facilities\RelationManagers\ProductsRelationManager;
use App\Filament\Resources\Pharmacies\Pages\CreatePharmacy;
use App\Filament\Resources\Pharmacies\Pages\EditPharmacy;
use App\Filament\Resources\Pharmacies\Pages\ListPharmacies;
use App\Filament\Resources\Pharmacies\Schemas\PharmacyForm;
use App\Filament\Resources\Pharmacies\Tables\PharmaciesTable;
use App\Models\Facility;
use App\Models\User;
use App\Policies\PharmacyPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PharmacyResource extends Resource
{
    protected static ?string $model = Facility::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Pharmacies';

    protected static string|UnitEnum|null $navigationGroup = 'Directory';

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

    /**
     * Every Filament ability — page access, row and bulk actions alike — goes
     * through PharmacyPolicy, so `pharmacies.*` applies rather than the
     * `facilities.*` the Gate would pick for the shared Facility model. The old
     * canEdit/canDelete overrides only covered page access; the actions
     * themselves (including bulk delete) still consulted FacilityPolicy.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $user = auth()->user();
        $ability = match (true) {
            $action instanceof BackedEnum => (string) $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };
        $policy = app(PharmacyPolicy::class);

        if (! $user instanceof User || ! method_exists($policy, $ability)) {
            return Response::deny();
        }

        $allowed = $record === null ? $policy->{$ability}($user) : $policy->{$ability}($user, $record);

        return $allowed ? Response::allow() : Response::deny();
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
