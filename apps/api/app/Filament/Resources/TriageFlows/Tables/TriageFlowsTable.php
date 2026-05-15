<?php

namespace App\Filament\Resources\TriageFlows\Tables;

use App\Filament\Tables\Filters\PublicationStatusFilter;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TriageFlowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                IconColumn::make('is_published')->boolean()->label('Published'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                PublicationStatusFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
