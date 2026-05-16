<?php

namespace App\Filament\Resources\Pharmacies\Tables;

use App\Filament\Support\DirectoryTableColumns;
use App\Filament\Tables\Filters\PublicationStatusFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PharmaciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                DirectoryTableColumns::hiddenSlug(),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Shelf items')
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
