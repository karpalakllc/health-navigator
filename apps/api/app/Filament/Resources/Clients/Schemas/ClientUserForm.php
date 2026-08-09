<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\ForumCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClientUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true)->disabledOn('edit'),
            Select::make('roles')
                ->relationship(
                    name: 'roles',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $query->where('name', 'Forum Moderator'),
                )
                ->multiple()
                ->preload()
                ->label('Community roles')
                ->helperText('Grants forum moderation in Filament (pin, lock, approve) without staff admin access.')
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
