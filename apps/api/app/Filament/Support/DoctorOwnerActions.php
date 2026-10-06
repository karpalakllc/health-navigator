<?php

namespace App\Filament\Support;

use App\Actions\DoctorAccount\AssignDoctorOwner;
use App\Actions\DoctorAccount\DoctorAccountException;
use App\Actions\DoctorAccount\RemoveDoctorOwner;
use App\Models\Doctor;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

/**
 * „Assign account“ / „Remove account“ on the doctor edit page: link the member
 * account that may manage this profile („Мој профил“ on the website). Verify
 * the person outside the platform first. Needs doctors.assign_owner.
 */
final class DoctorOwnerActions
{
    public static function assign(): Action
    {
        return Action::make('assignOwner')
            ->label('Assign account')
            ->icon('heroicon-o-user-plus')
            ->visible(fn (Doctor $record): bool => $record->owner_user_id === null && self::allowed($record))
            ->modalHeading('Assign a member account to this profile')
            ->modalDescription('The account can then edit the practice details (photo, bio, phone, hours, languages), ask for changes to the name, title, specialties and workplaces (staff approve them), and reply to reviews. Verify the person first, outside the platform.')
            ->modalSubmitActionLabel('Assign account')
            ->schema([
                Select::make('user_id')
                    ->label('Member account (email)')
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $search): array => self::searchAccounts($search))
                    ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value)?->email),
            ])
            ->action(function (Doctor $record, array $data): void {
                if (! self::allowed($record)) {
                    return;
                }

                $account = User::query()->find($data['user_id'] ?? null);

                if (! $account instanceof User) {
                    Notification::make()->title('Account not found')->danger()->send();

                    return;
                }

                try {
                    app(AssignDoctorOwner::class)->handle($record, $account, self::actor());
                } catch (DoctorAccountException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Account assigned')->success()->send();
            });
    }

    public static function remove(): Action
    {
        return Action::make('removeOwner')
            ->label('Remove account')
            ->icon('heroicon-o-user-minus')
            ->color('danger')
            ->visible(fn (Doctor $record): bool => $record->owner_user_id !== null && self::allowed($record))
            ->requiresConfirmation()
            ->modalHeading('Remove the managing account?')
            ->modalDescription(fn (Doctor $record): string => 'Unlinks '.($record->owner->email ?? 'the account').' from this profile. Its pending change requests and unapproved replies are withdrawn; approved replies stay.')
            ->action(function (Doctor $record): void {
                if (! self::allowed($record)) {
                    return;
                }

                app(RemoveDoctorOwner::class)->handle($record);

                Notification::make()->title('Account removed')->success()->send();
            });
    }

    /**
     * Active member accounts whose address matches, not already managing a
     * profile. Staff accounts cannot use the website's doctor dashboard.
     *
     * @return array<int, string>
     */
    public static function searchAccounts(string $search): array
    {
        $term = trim(mb_strtolower($search));

        if (mb_strlen($term) < 3) {
            return [];
        }

        return User::query()
            ->clients()
            ->whereNull('anonymised_at')
            ->whereNull('suspended_at')
            ->where('email', 'like', '%'.addcslashes($term, '%_\\').'%')
            ->whereNotIn('id', Doctor::withTrashed()->whereNotNull('owner_user_id')->select('owner_user_id'))
            ->orderBy('email')
            ->limit(20)
            ->pluck('email', 'id')
            ->all();
    }

    private static function allowed(Doctor $record): bool
    {
        return auth()->user()?->can('assignOwner', $record) ?? false;
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
