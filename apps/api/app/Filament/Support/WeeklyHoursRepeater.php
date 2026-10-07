<?php

namespace App\Filament\Support;

use App\Support\OfficeHours;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * A weekly-hours field stored as {day: hours} (doctors' and facilities'
 * office_hours, the urgent service's emergency_hours), edited as one row per
 * day.
 *
 * The rows are folded into the map in the repeater's *last* dehydration step.
 * Folding them earlier (dehydrateStateUsing) does not survive: the repeater
 * then runs its own mutateDehydratedState, array_values(), over the map and
 * the day labels become 0, 1, … (OfficeHoursRepeaterTest).
 */
final class WeeklyHoursRepeater
{
    public static function make(string $name, string $label, string $placeholder): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->schema([
                Select::make('day')
                    ->label('Day')
                    ->options(OfficeHours::DAY_OPTIONS)
                    ->required()
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                TextInput::make('hours')
                    ->label('Hours')
                    ->placeholder($placeholder)
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
            ->mutateDehydratedStateUsing(
                fn (Repeater $component, ?array $state): ?array => OfficeHours::fromRows($component->dehydrateItems($state)),
            )
            ->columnSpanFull();
    }
}
