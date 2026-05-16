<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\FacilityType;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PharmaciesRelationManager extends RelationManager
{
    protected static string $relationship = 'pharmacies';

    protected static ?string $title = 'Pharmacy offers';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                TextInput::make('currency')
                    ->default('MKD')
                    ->maxLength(3)
                    ->required(),
                Toggle::make('is_available')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('city'),
                TextColumn::make('pivot.price')
                    ->label('Price')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('pivot.currency')
                    ->label('Currency'),
                IconColumn::make('pivot.is_available')
                    ->label('Available')
                    ->boolean(),
                TextColumn::make('pivot.price_updated_at')
                    ->label('Price updated')
                    ->dateTime()
                    ->color(function ($record): ?string {
                        $updatedAt = $record->pivot->price_updated_at;

                        if ($updatedAt === null) {
                            return 'danger';
                        }

                        return $updatedAt < now()->subDays(30) ? 'warning' : null;
                    })
                    ->description(function ($record): ?string {
                        $updatedAt = $record->pivot->price_updated_at;

                        if ($updatedAt === null) {
                            return 'No update date';
                        }

                        if ($updatedAt < now()->subDays(30)) {
                            return 'Older than 30 days — verify price';
                        }

                        return null;
                    }),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(
                        fn ($query) => $query
                            ->where('type', FacilityType::Pharmacy)
                            ->orderBy('name'),
                    )
                    ->form([
                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        TextInput::make('currency')
                            ->default('MKD')
                            ->maxLength(3)
                            ->required(),
                        Toggle::make('is_available')
                            ->default(true),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['price_updated_at'] = now();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['price_updated_at'] = now();

                        return $data;
                    }),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
