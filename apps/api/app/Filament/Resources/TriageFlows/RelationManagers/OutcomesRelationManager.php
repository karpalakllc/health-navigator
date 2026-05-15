<?php

namespace App\Filament\Resources\TriageFlows\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OutcomesRelationManager extends RelationManager
{
    protected static string $relationship = 'outcomes';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->required()->maxLength(64),
                TextInput::make('title')->required()->maxLength(255),
                Textarea::make('body')->required()->rows(5)->columnSpanFull(),
                Repeater::make('handoffs')
                    ->schema([
                        Select::make('type')
                            ->options([
                                'home' => 'Home',
                                'doctors' => 'Doctors',
                                'facilities' => 'Facilities',
                                'emergency' => 'Emergency',
                            ])
                            ->required(),
                        TextInput::make('label')->maxLength(255),
                        TextInput::make('href')->maxLength(255),
                    ])
                    ->columnSpanFull()
                    ->defaultItems(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('code'),
                TextColumn::make('title'),
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
