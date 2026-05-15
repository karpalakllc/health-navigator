<?php

namespace App\Filament\Resources\TriageFlows\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RedFlagsRelationManager extends RelationManager
{
    protected static string $relationship = 'redFlags';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->required()->maxLength(64),
                TextInput::make('label')->required()->maxLength(255),
                TextInput::make('sort_order')->numeric()->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('code'),
                TextColumn::make('label'),
                TextColumn::make('sort_order'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
