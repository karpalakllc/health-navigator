<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Support\PublicWebUrl;
use App\Support\Slug;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if (filled($state)) {
                            $set('slug', Slug::fromName($state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('category')
                    ->maxLength(255),
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
