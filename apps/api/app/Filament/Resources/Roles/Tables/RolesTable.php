<?php

namespace App\Filament\Resources\Roles\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
