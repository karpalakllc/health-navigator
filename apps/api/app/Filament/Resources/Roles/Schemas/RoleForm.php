<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Support\PermissionCatalog;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            CheckboxList::make('permissions')
                ->relationship('permissions', 'name')
                ->options(
                    Permission::query()
                        ->whereIn('name', PermissionCatalog::all())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all(),
                )
                ->columns(2)
                ->searchable()
                ->bulkToggleable(),
        ]);
    }
}
