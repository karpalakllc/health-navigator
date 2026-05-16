<?php

namespace App\Filament\Resources\Staff;

use App\Filament\Resources\Staff\Pages\CreateStaffUser;
use App\Filament\Resources\Staff\Pages\EditStaffUser;
use App\Filament\Resources\Staff\Pages\ListStaffUsers;
use App\Filament\Resources\Staff\Schemas\StaffUserForm;
use App\Filament\Resources\Staff\Tables\StaffUsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Staff';

    protected static string|\UnitEnum|null $navigationGroup = 'Users';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

    public static function form(Schema $schema): Schema
    {
        return StaffUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffUsersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->staff();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffUsers::route('/'),
            'create' => CreateStaffUser::route('/create'),
            'edit' => EditStaffUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('staff.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('staff.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('staff.update') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('staff.delete') ?? false;
    }
}
