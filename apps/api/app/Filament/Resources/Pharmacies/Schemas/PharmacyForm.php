<?php

namespace App\Filament\Resources\Pharmacies\Schemas;

use App\Filament\Support\FacilityCommonForm;
use App\Support\Slug;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PharmacyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Fieldset::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::OutlinedBuildingStorefront)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if (filled($state)) {
                                    $set('slug', Slug::fromName($state));
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->prefixIcon(Heroicon::OutlinedLink)
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('avatar_url')
                            ->label('Image URL')
                            ->prefixIcon(Heroicon::OutlinedPhoto)
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                ...FacilityCommonForm::locationContactAndHoursFieldsets(),

                FacilityCommonForm::publishingFieldset(),
            ]);
    }
}
