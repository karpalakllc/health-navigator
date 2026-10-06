<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Usernames\UsernameValidator;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A sign-up's chosen username is held only once the address is verified —
 * by the link, by settling a contested registration, or by a password reset
 * (every path fires Verified). Until then the account carries a temporary
 * „clen-…“ name, so the availability check cannot tell a new address from a
 * registered one (AuthController::register()).
 *
 * If someone took the name (or a look-alike) in the meantime, or a list
 * update now refuses it, the account keeps its temporary name and the member
 * is asked to choose at sign-in (`must_choose_username`, already set).
 *
 * Registered by Laravel's listener discovery (see RevokeApiTokensOnPasswordReset).
 */
class AssignRequestedUsername
{
    public function handle(Verified $event): void
    {
        if (! $event->user instanceof User || $event->user->requested_username === null) {
            return;
        }

        $user = $event->user;

        try {
            DB::transaction(function () use ($user): void {
                /** @var User $locked */
                $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $requested = $locked->requested_username;

                if ($requested === null) {
                    return;
                }

                $locked->requested_username = null;

                if (UsernameValidator::problem($requested, $locked) === null) {
                    $locked->username = $requested;
                    $locked->must_choose_username = false;
                }

                $locked->save();
                $user->setRawAttributes($locked->getAttributes(), true);
            });
        } catch (UniqueConstraintViolationException) {
            // Taken a moment ago by someone else: keep the temporary name.
            User::query()->whereKey($user->getKey())->update(['requested_username' => null]);
            $user->forceFill(['requested_username' => null])->syncOriginal();
        }
    }
}
