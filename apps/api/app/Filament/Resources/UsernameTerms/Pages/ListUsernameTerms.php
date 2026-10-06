<?php

namespace App\Filament\Resources\UsernameTerms\Pages;

use App\Filament\Resources\UsernameTerms\UsernameTermResource;
use App\Models\UsernameTerm;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameTermMatcher;
use App\Support\Usernames\UsernameValidator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUsernameTerms extends ListRecords
{
    protected static string $resource = UsernameTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Tracing a reported false refusal to its term, or checking a new
            // term before adding it. Staff only, so it may name the term.
            Action::make('testUsername')
                ->label('Test a username')
                ->icon('heroicon-o-beaker')
                ->modalSubmitActionLabel('Test')
                ->schema([
                    TextInput::make('username')->required()->maxLength(100),
                ])
                ->action(function (array $data): void {
                    $username = UsernameNormalizer::prepare((string) $data['username']);
                    $format = UsernameValidator::formatProblem($username);
                    $terms = UsernameTermMatcher::explain($username);

                    $body = 'Reads as „'.UsernameNormalizer::key($username).'“, looks like „'.UsernameNormalizer::skeleton($username).'“.';

                    if ($format !== null) {
                        $body .= ' Not a valid username ('.$format.').';
                    }

                    Notification::make()
                        ->title($terms === [] ? 'Not refused by the lists' : 'Refused by the lists')
                        ->body($terms === []
                            ? $body
                            : $body.' Matching: '.collect($terms)->map(fn (UsernameTerm $term): string => "„{$term->term}“ ({$term->kind->value}, {$term->match_type->value})")->implode(', ').'.')
                        ->color($terms === [] ? 'success' : 'warning')
                        ->persistent()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
