<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\ForumCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class ClientUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->disabledOn('edit'),
            Select::make('roles')
                ->relationship('roles', 'name')
                ->multiple()
                ->preload()
                ->options(
                    Role::query()
                        ->whereIn('name', ['Forum Moderator'])
                        ->orderBy('name')
                        ->pluck('name', 'id'),
                )
                ->label('Community roles')
                ->visible(fn (): bool => auth()->user()?->can('clients.assign_roles') ?? false),
            Select::make('moderatedForumCategories')
                ->relationship('moderatedForumCategories', 'name')
                ->multiple()
                ->preload()
                ->options(ForumCategory::query()->orderBy('name')->pluck('name', 'id'))
                ->label('Forum categories (scoped moderation)')
                ->helperText('Leave empty for full forum moderation when the role allows it.'),
            TextInput::make('password')
                ->password()
                ->dehydrated(fn (?string $state): bool => filled($state)),
        ]);
    }
}
