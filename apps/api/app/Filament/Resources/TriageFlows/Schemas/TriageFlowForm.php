<?php

namespace App\Filament\Resources\TriageFlows\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TriageFlowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('intro_body')
                    ->rows(4)
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->helperText('Only one flow may be published at a time.'),
            ]);
    }
}
