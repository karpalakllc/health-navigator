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
use Illuminate\Auth\Access\Response;
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

    // Only the kind-specific gates live here. Edit and delete go through
    // UserPolicy, which also enforces the privilege hierarchy — an override of
    // canEdit()/canDelete() would let page access skip it.

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return (auth()->user()?->can('clients.view') ?? false) ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return (auth()->user()?->can('clients.create') ?? false) ? Response::allow() : Response::deny();
    }
}
