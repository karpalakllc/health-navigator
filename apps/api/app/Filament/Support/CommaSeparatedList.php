<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;

final class CommaSeparatedList
{
    public static function make(string $name, string $label): Textarea
    {
        return Textarea::make($name)
            ->label($label)
            ->rows(2)
            ->helperText('Comma-separated values.')
            ->formatStateUsing(function ($state): string {
                if (is_array($state)) {
                    return implode(', ', $state);
                }

                return is_string($state) ? $state : '';
            })
            ->dehydrateStateUsing(function (?string $state): ?array {
                if ($state === null || trim($state) === '') {
                    return null;
                }

                $items = array_values(array_filter(array_map(
                    static fn (string $item): string => trim($item),
                    explode(',', $state),
                )));

                return $items === [] ? null : $items;
            });
    }
}
