<?php

namespace App\Filament\Support;

use App\Support\EmailAddress;
use Filament\Forms\Components\TextInput;

/**
 * An email input whose value is normalised before it is validated, not only
 * when the model stores it.
 *
 * The User model lowercases on write, but `->unique()` checked the value as
 * typed: "DUP@example.com" passed against an existing "dup@example.com", then
 * hit the unique index after the mutator lowercased it — a 500 rather than a
 * form error.
 */
final class EmailField
{
    public static function make(string $name = 'email'): TextInput
    {
        $normalise = fn (?string $state): ?string => $state === null ? null : EmailAddress::normalize($state);

        return TextInput::make($name)
            ->email()
            ->mutateStateForValidationUsing($normalise)
            ->dehydrateStateUsing($normalise);
    }
}
