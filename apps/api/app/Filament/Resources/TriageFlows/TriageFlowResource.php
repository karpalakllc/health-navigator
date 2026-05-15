<?php

namespace App\Filament\Resources\TriageFlows;

use App\Filament\Resources\TriageFlows\Pages\CreateTriageFlow;
use App\Filament\Resources\TriageFlows\Pages\EditTriageFlow;
use App\Filament\Resources\TriageFlows\Pages\ListTriageFlows;
use App\Filament\Resources\TriageFlows\RelationManagers\OutcomesRelationManager;
use App\Filament\Resources\TriageFlows\RelationManagers\RedFlagsRelationManager;
use App\Filament\Resources\TriageFlows\RelationManagers\RulesRelationManager;
use App\Filament\Resources\TriageFlows\RelationManagers\StepsRelationManager;
use App\Filament\Resources\TriageFlows\Schemas\TriageFlowForm;
use App\Filament\Resources\TriageFlows\Tables\TriageFlowsTable;
use App\Models\TriageFlow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TriageFlowResource extends Resource
{
    protected static ?string $model = TriageFlow::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'Symptom guidance';

    protected static string|\UnitEnum|null $navigationGroup = 'Guidance';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return TriageFlowForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TriageFlowsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StepsRelationManager::class,
            RedFlagsRelationManager::class,
            RulesRelationManager::class,
            OutcomesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTriageFlows::route('/'),
            'create' => CreateTriageFlow::route('/create'),
            'edit' => EditTriageFlow::route('/{record}/edit'),
        ];
    }
}
