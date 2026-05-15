<?php

namespace App\Filament\Resources\TriageFlows\RelationManagers;

use App\Support\Triage\TriageRuleConditionsValidator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('priority')
                    ->numeric()
                    ->default(100)
                    ->helperText('Lower numbers run first.'),
                TextInput::make('outcome_code')
                    ->required()
                    ->maxLength(64),
                Textarea::make('conditions')
                    ->required()
                    ->rows(8)
                    ->helperText(
                        'JSON object. Single condition: {"step":"severity","operator":"in","values":["severe"]}. '
                        .'Multiple (AND): {"all":[{"step":"duration","operator":"in","values":["over_week"]},...]}. '
                        .'Operators: in, eq, includes_any. "step" must match a step_key in this flow.',
                    )
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                    ->dehydrateStateUsing(function ($state) {
                        if (is_array($state)) {
                            return TriageRuleConditionsValidator::validate($state);
                        }

                        try {
                            $decoded = json_decode((string) $state, true, 512, JSON_THROW_ON_ERROR);
                        } catch (\JsonException $exception) {
                            throw ValidationException::withMessages([
                                'conditions' => 'Invalid JSON: '.$exception->getMessage(),
                            ]);
                        }

                        return TriageRuleConditionsValidator::validate($decoded);
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('priority')->sortable(),
                TextColumn::make('outcome_code'),
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
