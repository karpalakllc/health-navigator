<?php

namespace App\Filament\Resources\TriageFlows\RelationManagers;

use App\Enums\TriageStepType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('step_key')
                    ->required()
                    ->maxLength(64)
                    ->helperText('Stable key used in rules, e.g. severity'),
                TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->options(collect(TriageStepType::cases())->mapWithKeys(
                        fn (TriageStepType $type) => [$type->value => str_replace('_', ' ', $type->value)],
                    ))
                    ->required()
                    ->default(TriageStepType::SingleSelect->value),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Toggle::make('is_required')->default(true),
                Repeater::make('options')
                    ->relationship()
                    ->schema([
                        TextInput::make('value')->required()->maxLength(64),
                        TextInput::make('label')->required()->maxLength(255),
                        TextInput::make('sort_order')->numeric()->default(0),
                    ])
                    ->columnSpanFull()
                    ->defaultItems(2)
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('step_key'),
                TextColumn::make('label'),
                TextColumn::make('type'),
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
