<?php

namespace App\Filament\Support;

use App\Support\OfficeHours;
use App\Support\PublicWebUrl;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Icons\Heroicon;

final class FacilityCommonForm
{
    /**
     * @return array<int, Fieldset>
     */
    public static function locationContactAndHoursFieldsets(): array
    {
        return [
            Fieldset::make('Location')
                ->columns(2)
                ->schema([
                    TextInput::make('city')
                        ->prefixIcon(Heroicon::OutlinedMapPin)
                        ->maxLength(255),
                    TextInput::make('address')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('latitude')
                        ->label('Latitude')
                        ->numeric()
                        ->minValue(-90)
                        ->maxValue(90)
                        ->step(0.0000001)
                        ->helperText('For map on the public site (e.g. 41.0311).'),
                    TextInput::make('longitude')
                        ->label('Longitude')
                        ->numeric()
                        ->minValue(-180)
                        ->maxValue(180)
                        ->step(0.0000001)
                        ->helperText('For map on the public site (e.g. 21.3403).'),
                ]),
            Fieldset::make('Contact')
                ->columns(2)
                ->schema([
                    TextInput::make('phone')
                        ->tel()
                        ->prefixIcon(Heroicon::OutlinedPhone)
                        ->maxLength(50),
                    TextInput::make('email')
                        ->email()
                        ->prefixIcon(Heroicon::OutlinedEnvelope)
                        ->maxLength(255),
                    TextInput::make('website')
                        ->url()
                        ->prefixIcon(Heroicon::OutlinedGlobeAlt)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),
            Fieldset::make('Opening hours')
                ->schema([
                    Repeater::make('office_hours')
                        ->label('Weekly schedule')
                        ->schema([
                            Select::make('day')
                                ->label('Day')
                                ->options(OfficeHours::DAY_OPTIONS)
                                ->required()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            TextInput::make('hours')
                                ->label('Hours')
                                ->placeholder('08:00–18:00')
                                ->required()
                                ->maxLength(100),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Add day')
                        ->reorderable(false)
                        ->formatStateUsing(
                            fn ($state) => is_array($state) && array_is_list($state)
                                ? $state
                                : OfficeHours::toRows(is_array($state) ? $state : null),
                        )
                        ->dehydrateStateUsing(
                            fn ($state) => OfficeHours::fromRows(is_array($state) ? $state : null),
                        )
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function publishingFieldset(): Fieldset
    {
        return Fieldset::make('Publishing')
            ->schema([
                Toggle::make('is_published')
                    ->default(false)
                    ->helperText(function ($record): ?string {
                        if (! $record?->is_published || ! PublicWebUrl::configured()) {
                            return null;
                        }

                        $url = PublicWebUrl::forRecord($record);

                        return $url ? "Public: {$url}" : null;
                    }),
            ]);
    }
}
