<?php

namespace App\Filament\Support;

use App\Models\User;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameValidator;
use Closure;
use Filament\Forms\Components\TextInput;

/**
 * The username on the staff and client forms. Optionally set when staff create
 * an account, through the same rules as registration (UsernameValidator) —
 * left empty, the account gets a temporary name to replace; after
 * that it is read-only here, and changed with RenameUsernameAction, which
 * keeps the old name reserved and records why.
 */
final class UsernameField
{
    public static function make(): TextInput
    {
        return TextInput::make('username')
            ->label('Username (public)')
            ->maxLength(UsernameValidator::MAX_LENGTH)
            ->disabled(fn (string $operation): bool => $operation !== 'create')
            ->dehydrated(fn (string $operation): bool => $operation === 'create')
            ->rule(fn (?User $record): Closure => self::rule($record))
            ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : UsernameNormalizer::prepare($state))
            ->helperText(fn (string $operation): string => $operation === 'create'
                ? 'Shown next to everything the account posts; the real name above stays private. Leave empty and the person chooses one at their first sign-in on the website.'
                : 'Shown publicly. Change it with „Rename username“ (kept in the history, the old name stays reserved for 6 months).');
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    public static function rule(?User $owner): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($owner): void {
            $problem = is_string($value) ? UsernameValidator::problem($value, $owner) : 'alphabet';

            if ($problem !== null) {
                $fail(__("validation.custom.username.{$problem}"));
            }
        };
    }
}
