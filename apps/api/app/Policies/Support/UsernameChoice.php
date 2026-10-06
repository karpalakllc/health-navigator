<?php

namespace App\Policies\Support;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A member who still has a temporary „clen-…“ username (accounts from before
 * usernames, or renamed by staff) may read everything but not post: reviews,
 * forum topics and replies, and „Корисно“ votes wait until they have chosen
 * the name all of it is shown under. The denial carries the message the web
 * app shows next to the form.
 */
final class UsernameChoice
{
    public static function gate(User $user): Response
    {
        return $user->must_choose_username
            ? Response::deny(__('api.username.required'))
            : Response::allow();
    }
}
