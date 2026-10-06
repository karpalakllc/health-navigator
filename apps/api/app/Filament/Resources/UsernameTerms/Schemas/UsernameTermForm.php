<?php

namespace App\Filament\Resources\UsernameTerms\Schemas;

use App\Enums\UsernameMatchType;
use App\Enums\UsernameTermKind;
use App\Models\UsernameTerm;
use App\Support\Usernames\UsernameNormalizer;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UsernameTermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('term')
                ->required()
                ->maxLength(100)
                ->helperText('Any script and spelling: „пичка“ also covers pichka, p1chka and пи-чка. Spaces and . _ - are ignored.')
                ->rule(fn (Get $get, ?UsernameTerm $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                    if (! is_string($value) || UsernameNormalizer::key($value) === '') {
                        $fail('The term must contain letters or digits.');

                        return;
                    }

                    $duplicate = UsernameTerm::query()
                        ->where('kind', $get('kind'))
                        ->where('match_type', $get('match_type'))
                        ->where('term', mb_strtolower(UsernameNormalizer::prepare($value)))
                        ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                        ->exists();

                    if ($duplicate) {
                        $fail('This term is already on the list with this match type.');
                    }
                }),
            Select::make('kind')
                ->options(collect(UsernameTermKind::cases())->mapWithKeys(fn (UsernameTermKind $kind): array => [$kind->value => $kind->label()])->all())
                ->required()
                ->default(UsernameTermKind::Blocked->value)
                ->helperText('Blocked: offensive words. Reserved: names that would impersonate staff, the platform, a doctor or an authority. Members get the same message for both.'),
            Select::make('match_type')
                ->label('Match')
                ->options(collect(UsernameMatchType::cases())->mapWithKeys(fn (UsernameMatchType $type): array => [$type->value => $type->label()])->all())
                ->required()
                ->default(UsernameMatchType::Exact->value)
                ->live()
                ->helperText('Exact: the whole username or one of its words („dr.marko“). Contains: anywhere — only for words that never occur inside real names. Allowed: an exception for a name or word that contains a listed term (e.g. „therapist“).'),
            // Short `contains` terms refuse ordinary names (the Scunthorpe
            // problem): staff have to say they mean it.
            Checkbox::make('confirm_short_contains')
                ->label('I know this short term will also refuse names that merely contain it')
                ->dehydrated(false)
                ->visible(fn (Get $get): bool => $get('match_type') === UsernameMatchType::Contains->value)
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    $term = $get('term');

                    if (is_string($term) && mb_strlen(UsernameNormalizer::key($term)) < UsernameMatchType::CONTAINS_MIN_LENGTH && $value !== true) {
                        $fail('Terms shorter than '.UsernameMatchType::CONTAINS_MIN_LENGTH.' letters should be „Exact word“; tick the box to use „Contains“ anyway.');
                    }
                }),
            Select::make('language')
                ->options(['en' => 'English', 'mk' => 'Macedonian', 'sq' => 'Albanian', 'any' => 'Any / names'])
                ->required()
                ->default('any'),
            Select::make('category')
                ->options(UsernameTerm::CATEGORIES),
            Textarea::make('note')
                ->maxLength(255)
                ->helperText('Staff only: why it is listed, or where a false refusal was reported.'),
            Toggle::make('active')
                ->default(true)
                ->helperText('Inactive terms stay listed but refuse nothing.'),
        ]);
    }
}
