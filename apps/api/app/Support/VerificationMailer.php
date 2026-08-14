<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Single gate for sending a verification link.
 *
 * The cooldown used to live on the /auth/email/resend route, which meant
 * /auth/register bypassed it entirely: four registrations for one address
 * delivered four mails. Anything that can cause a verification email must go
 * through here, so the limit is a property of the address rather than of
 * whichever endpoint happened to be called.
 */
final class VerificationMailer
{
    private const PER_ADDRESS_PER_HOUR = 3;

    private const WINDOW_SECONDS = 3600;

    /**
     * @return bool whether a mail was actually dispatched
     */
    public static function send(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $key = self::key($user->getEmailForVerification());

        if (RateLimiter::tooManyAttempts($key, self::PER_ADDRESS_PER_HOUR)) {
            return false;
        }

        RateLimiter::hit($key, self::WINDOW_SECONDS);

        $user->sendEmailVerificationNotification();

        return true;
    }

    private static function key(string $email): string
    {
        return 'verification-mail:'.sha1(mb_strtolower(trim($email)));
    }
}
