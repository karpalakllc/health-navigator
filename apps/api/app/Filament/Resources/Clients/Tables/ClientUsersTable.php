<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Enums\UserRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->label('Account type')
                    ->formatStateUsing(fn (UserRole|string|null $state): string => ucfirst(
                        $state instanceof UserRole ? $state->value : (string) $state,
                    )),
                TextColumn::make('roles.name')->badge()->label('Community roles'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
