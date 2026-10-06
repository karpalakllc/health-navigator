<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Filament\Support\EmailField;
use App\Filament\Support\UsernameField;
use App\Models\ForumCategory;
use App\Models\User;
use App\Policies\Support\PrivilegeHierarchy;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class ClientUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->helperText('Private: shown on the account page and here, never publicly.'),
            UsernameField::make(),
            EmailField::make()->required()->unique(ignoreRecord: true)->disabledOn('edit'),
            Select::make('roles')
                ->relationship(
                    name: 'roles',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $query->where('name', 'Forum Moderator'),
                )
                ->multiple()
                ->preload()
                // Same server-side check as the staff form: holding
                // clients.assign_roles is not enough to hand out a role whose
                // permissions the assigner does not hold (PrivilegeHierarchy).
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $actor = auth()->user();

                    if (! $actor instanceof User || ! PrivilegeHierarchy::canGrantRoleIds($actor, (array) $value)) {
                        $fail('You cannot assign a role with permissions you do not hold.');
                    }
                })
                ->label('Community roles')
                ->helperText('Grants forum moderation in Filament (pin, lock, approve) without staff admin access.')
                ->visible(fn (): bool => auth()->user()?->can('clients.assign_roles') ?? false),
            Select::make('moderatedForumCategories')
                ->relationship('moderatedForumCategories', 'name')
                ->multiple()
                ->preload()
                ->options(ForumCategory::query()->orderBy('name')->pluck('name', 'id'))
                ->label('Forum categories (scoped moderation)')
                ->helperText('Leave empty for full forum moderation when the role allows it.')
                // Scoping moderation is part of assigning the role, so it needs the same
                // permission; a hidden field is neither dehydrated nor saved.
                ->visible(fn (): bool => auth()->user()?->can('clients.assign_roles') ?? false),
            TextInput::make('password')
                ->password()
                ->rule(Password::defaults())
                ->dehydrated(fn (?string $state): bool => filled($state)),
        ]);
    }
}
