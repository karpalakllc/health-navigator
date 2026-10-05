<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;

/**
 * A password reset is how someone recovers an account another party may be
 * using. Changing the password alone leaves every bearer token that party holds
 * working until it expires, so the reset has to end them too.
 *
 * Registered by Laravel's listener discovery (app/Listeners, on by default via
 * Application::configure()->withEvents()). Do not also Event::listen() it in a
 * provider — that would register it twice.
 */
class RevokeApiTokensOnPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $event->user->tokens()->delete();
        }
    }
}
