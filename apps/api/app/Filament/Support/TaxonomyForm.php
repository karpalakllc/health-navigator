<?php

namespace App\Filament\Support;

use App\Support\Slug;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Set;

final class TaxonomyForm
{
    /**
     * @return array<int, Fieldset>
     */
    public static function schema(): array
    {
        return [
            Fieldset::make('Taxonomy entry')
                ->columns(2)
                ->schema(self::fields()),
        ];
    }

    /**
     * @return array<int, TextInput|Toggle>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->helperText('Display name shown on the public site.')
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    if (filled($state)) {
                        $set('slug', Slug::fromName($state));
                    }
                })
                ->columnSpanFull(),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('URL segment. Auto-generated from name; edit only when necessary.'),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->helperText('Lower numbers appear first in lists.'),
            Toggle::make('is_published')
                ->default(true)
                ->helperText('Unpublished taxonomies are hidden from the public API.'),
        ];
    }
}
