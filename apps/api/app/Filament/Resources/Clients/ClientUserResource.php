<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\EditClientUser;
use App\Filament\Resources\Clients\Pages\ListClientUsers;
use App\Filament\Resources\Clients\Schemas\ClientUserForm;
use App\Filament\Resources\Clients\Tables\ClientUsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Clients';

    protected static string|\UnitEnum|null $navigationGroup = 'Users';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'client';

    protected static ?string $pluralModelLabel = 'clients';

    public static function form(Schema $schema): Schema
    {
        return ClientUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientUsersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->clients();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientUsers::route('/'),
            'edit' => EditClientUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('clients.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('clients.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('clients.update') ?? false;
    }
}
