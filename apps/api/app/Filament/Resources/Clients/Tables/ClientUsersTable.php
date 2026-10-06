<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Support\SuspendAccountActions;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('display_name')->label('Public name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')->badge()->label('Roles'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (User $record): string => match (true) {
                        $record->isAnonymised() => 'Deleted',
                        $record->isSuspended() => 'Suspended',
                        default => 'Active',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Suspended' => 'danger',
                        'Deleted' => 'gray',
                        default => 'success',
                    })
                    ->tooltip(fn (User $record): ?string => $record->isSuspended() ? $record->suspension_reason : null),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('suspended')
                    ->label('Suspended')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('suspended_at'),
                        false: fn (Builder $query) => $query->whereNull('suspended_at'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                SuspendAccountActions::suspend(),
                SuspendAccountActions::unsuspend(),
            ]);
    }
}
