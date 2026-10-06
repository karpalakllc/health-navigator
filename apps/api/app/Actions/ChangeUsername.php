<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UsernameHistory;
use App\Support\Usernames\TemporaryUsername;
use App\Support\Usernames\UsernameNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gives an account a new username — the member's own choice (account page,
 * first-sign-in chooser) or a staff rename (admin panel). Validation
 * (UsernameValidator) and the member's 90-day limit are the caller's job;
 * this records the change:
 *
 * - the old name goes to UsernameHistory and stays reserved for six months,
 *   unless it was a temporary „clen-…“ name nobody chose;
 * - a member's own rename starts the 90-day limit, except the first choice
 *   that replaces a temporary name;
 * - a staff rename (`$by`) keeps the reason in the history row and does not
 *   touch the member's limit. Without a new name the account gets a
 *   temporary one and the member chooses again at the next sign-in.
 */
final class ChangeUsername
{
    public function handle(User $user, ?string $username, ?User $by = null, ?string $note = null): User
    {
        try {
            return DB::transaction(function () use ($user, $username, $by, $note): User {
                /** @var User $locked */
                $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

                $old = $locked->username;
                $new = $username === null || trim($username) === '' ? null : UsernameNormalizer::prepare($username);

                if ($new !== null && $new === $old) {
                    return $locked;
                }

                if ($old !== null && ! TemporaryUsername::isTemporary($old)) {
                    UsernameHistory::query()->create([
                        'user_id' => $locked->getKey(),
                        'username' => $old,
                        'username_normalized' => UsernameNormalizer::key($old),
                        'username_skeleton' => UsernameNormalizer::skeleton($old),
                        'reason' => $by === null ? 'changed' : 'forced',
                        'note' => $note,
                        'changed_by_id' => $by?->getKey(),
                        'reserved_until' => now()->addMonths(UsernameHistory::HOLD_MONTHS),
                    ]);
                }

                if ($by !== null) {
                    $locked->forceFill([
                        'username' => $new ?? TemporaryUsername::generate(),
                        'must_choose_username' => $new === null,
                    ])->save();
                } else {
                    $locked->forceFill([
                        'username' => $new,
                        'username_changed_at' => $locked->must_choose_username ? $locked->username_changed_at : now(),
                        'must_choose_username' => false,
                    ])->save();
                }

                $user->setRawAttributes($locked->getAttributes(), true);

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            // Lost a race with someone taking the same name a moment earlier.
            throw ValidationException::withMessages([
                'username' => [__('validation.custom.username.taken')],
            ]);
        }
    }
}
