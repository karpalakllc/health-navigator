<?php

namespace App\Filament\Resources\Facilities\Tables;

use App\Enums\FacilityType;
use App\Filament\Support\DirectoryTableColumns;
use App\Filament\Tables\Filters\PublicationStatusFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class FacilitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                DirectoryTableColumns::hiddenSlug(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (FacilityType $state): string => ucfirst($state->value))
                    ->color(fn (FacilityType $state): string => match ($state) {
                        FacilityType::Hospital => 'danger',
                        FacilityType::Clinic => 'info',
                        FacilityType::Laboratory => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('has_emergency_services')
                    ->label('Emergency')
                    ->boolean()
                    ->sortable(),
                DirectoryTableColumns::publicationBadge(),
                DirectoryTableColumns::updatedAt(),
            ])
            ->defaultSort('name')
            ->filters([
                PublicationStatusFilter::make(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
