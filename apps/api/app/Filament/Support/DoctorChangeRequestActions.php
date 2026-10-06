<?php

namespace App\Filament\Support;

use App\Actions\DoctorAccount\DecideDoctorChangeRequest;
use App\Actions\DoctorAccount\DoctorAccountException;
use App\Models\DoctorChangeRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Approve / reject a linked doctor's change request, shared by the queue's
 * rows and the view page. Approving writes the new values to the profile;
 * the doctor is emailed either way (the reason on a rejection).
 */
final class DoctorChangeRequestActions
{
    /** Bullet lines „Field: old → new“ for the queue and the view page. */
    public static function summary(DoctorChangeRequest $record): array
    {
        $lines = [];

        foreach ($record->changes as $field => $change) {
            $lines[] = self::label((string) $field).': '.self::value($change['old'] ?? null).' → '.self::value($change['new'] ?? null);
        }

        return $lines;
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve and apply')
            ->color('success')
            ->icon('heroicon-o-check')
            ->visible(fn (DoctorChangeRequest $record): bool => self::canDecide($record))
            ->requiresConfirmation()
            ->modalDescription('Writes the requested values to the public profile and emails the doctor.')
            ->action(function (DoctorChangeRequest $record): void {
                if (! self::canDecide($record)) {
                    return;
                }

                app(DecideDoctorChangeRequest::class)->approve($record, self::actor());

                Notification::make()->title('Change request approved')->success()->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->color('danger')
            ->icon('heroicon-o-x-mark')
            ->visible(fn (DoctorChangeRequest $record): bool => self::canDecide($record))
            ->modalDescription('The profile keeps its current values. The doctor is emailed this reason — write it in Macedonian.')
            ->schema([
                Textarea::make('rejection_reason')
                    ->label('Reason (sent to the doctor)')
                    ->required()
                    ->minLength(5)
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (DoctorChangeRequest $record, array $data): void {
                if (! self::canDecide($record)) {
                    return;
                }

                try {
                    app(DecideDoctorChangeRequest::class)->reject($record, self::actor(), (string) ($data['rejection_reason'] ?? ''));
                } catch (DoctorAccountException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Change request rejected')->success()->send();
            });
    }

    private static function canDecide(DoctorChangeRequest $record): bool
    {
        return $record->isPending() && (auth()->user()?->can('update', $record) ?? false);
    }

    private static function label(string $field): string
    {
        return match ($field) {
            'full_name' => 'Full name',
            'title' => 'Title',
            'subspecialty' => 'Subspecialty',
            'education' => 'Education',
            'years_experience' => 'Years of experience',
            'city' => 'City',
            'specialties' => 'Specialties',
            'facilities' => 'Workplaces',
            default => $field,
        };
    }

    private static function value(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }

        if (is_array($value)) {
            return implode(', ', array_map(
                fn ($link): string => is_array($link)
                    ? ((string) ($link['name'] ?? $link['id'] ?? '')).(! empty($link['is_primary']) ? ' (primary)' : '')
                    : (string) $link,
                $value,
            ));
        }

        return (string) $value;
    }

    private static function actor(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
