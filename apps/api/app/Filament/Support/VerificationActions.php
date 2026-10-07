<?php

namespace App\Filament\Support;

use App\Models\Doctor;
use App\Models\Facility;
use App\Models\User;
use App\Support\Verification\VerificationBasis;
use App\Support\Verification\VerificationWriter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * „Верификуван“ / „Неверификуван“ on the doctor, facility and pharmacy edit
 * pages. A staff decision needs a reason, goes to the audit log (log
 * „verification“, VerificationWriter) and is kept by later automatic import
 * runs until staff hand the profile back with „Return to automatic“.
 * Anyone who may edit the profile may decide.
 */
final class VerificationActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [self::verify(), self::unverify(), self::release()];
    }

    public static function verify(): Action
    {
        return Action::make('verifyProfile')
            ->label(fn (Doctor|Facility $record): string => $record->isVerified() ? 'Change verification' : 'Verify')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (Doctor|Facility $record): bool => self::allowed($record))
            ->modalHeading('Mark the profile as verified')
            ->modalDescription(fn (Doctor|Facility $record): string => self::summary($record)
                .' The public badge reads „Верификуван“ (places: „Верификувана“) with the basis below. Automatic import runs will not change this decision until you return the profile to automatic checks.')
            ->modalSubmitActionLabel('Verify')
            ->fillForm(fn (Doctor|Facility $record): array => [
                'basis' => ($record->verification_basis ?? VerificationBasis::Staff)->value,
            ])
            ->schema([
                Select::make('basis')
                    ->label('Basis (shown publicly)')
                    ->options(VerificationBasis::options())
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason (internal, audit log)')
                    ->helperText('What you checked, e.g. "Called the clinic on 7 Oct, confirmed she works there".')
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(function (Doctor|Facility $record, array $data): void {
                abort_unless(self::allowed($record), 403);

                app(VerificationWriter::class)->verify(
                    $record,
                    VerificationBasis::from((string) $data['basis']),
                    [],
                    self::actor(),
                    trim((string) $data['reason']),
                );

                Notification::make()->title('Profile verified')->success()->send();
            });
    }

    public static function unverify(): Action
    {
        return Action::make('unverifyProfile')
            ->label('Unverify')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->visible(fn (Doctor|Facility $record): bool => self::allowed($record)
                && ($record->isVerified() || ! $record->hasStaffVerificationDecision()))
            ->modalHeading('Mark the profile as unverified')
            ->modalDescription(fn (Doctor|Facility $record): string => self::summary($record)
                .' The public badge reads „Неверификуван“ (places: „Неверификувана“). Automatic import runs will not verify it again until you return the profile to automatic checks.')
            ->modalSubmitActionLabel('Unverify')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (internal, audit log)')
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(function (Doctor|Facility $record, array $data): void {
                abort_unless(self::allowed($record), 403);

                app(VerificationWriter::class)->unverify($record, trim((string) $data['reason']), self::actor());

                Notification::make()->title('Profile marked unverified')->success()->send();
            });
    }

    public static function release(): Action
    {
        return Action::make('releaseVerification')
            ->label('Return to automatic checks')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (Doctor|Facility $record): bool => self::allowed($record) && $record->hasStaffVerificationDecision())
            ->requiresConfirmation()
            ->modalHeading('Return verification to automatic checks?')
            ->modalDescription(fn (Doctor|Facility $record): string => self::summary($record)
                .' The current status stays until the next import run re-evaluates the profile from its sources.')
            ->action(function (Doctor|Facility $record): void {
                abort_unless(self::allowed($record), 403);

                app(VerificationWriter::class)->release($record, self::actor());

                Notification::make()->title('Returned to automatic checks')->success()->send();
            });
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('verified_at')
            ->label('Verification')
            ->badge()
            ->placeholder('Unverified')
            ->formatStateUsing(fn ($state, Doctor|Facility $record): string => $record->isVerified()
                ? 'Verified'.($record->hasStaffVerificationDecision() ? ' (staff)' : '')
                : 'Unverified')
            ->color(fn (Doctor|Facility $record): string => $record->isVerified() ? 'success' : 'gray')
            ->tooltip(fn (Doctor|Facility $record): ?string => $record->verification_basis?->label())
            ->sortable();
    }

    public static function filter(): TernaryFilter
    {
        return TernaryFilter::make('verified')
            ->label('Verification')
            ->trueLabel('Verified')
            ->falseLabel('Unverified')
            ->queries(
                true: fn (Builder $query) => $query->whereNotNull('verified_at'),
                false: fn (Builder $query) => $query->whereNull('verified_at'),
                blank: fn (Builder $query) => $query,
            );
    }

    /** One line for the modals: current status, basis, who decided, last reason. */
    private static function summary(Doctor|Facility $record): string
    {
        $reasons = $record->verification_reasons ?? [];
        $parts = [$record->isVerified()
            ? 'Now: verified ('.($record->verification_basis?->label() ?? 'unknown basis').')'
            : 'Now: unverified'];

        if ($record->verification_source !== null) {
            $parts[] = $record->hasStaffVerificationDecision() ? 'decided by staff' : 'decided automatically';
        }

        $last = $reasons['note'] ?? $reasons['reason'] ?? null;

        if (is_string($last) && $last !== '') {
            $parts[] = 'last reason: '.$last;
        }

        return implode(' · ', $parts).'.';
    }

    private static function allowed(Doctor|Facility $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
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
