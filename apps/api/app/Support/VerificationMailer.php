<?php

namespace App\Support;

use App\Mail\AccountExistsMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Single gate for every message the sign-up flow can cause to be delivered to an
 * address nobody has proved they own.
 *
 * Both messages here — the verification link and the "you already have an
 * account" notice — are triggerable by an anonymous caller who types someone
 * else's address into the sign-up form. Anything that can be triggered that way
 * has to be metered per address rather than per route, or the caller just picks
 * a different endpoint. Registration bypassed a route-level resend limit exactly
 * that way, and AccountExistsMail then bypassed the gate that replaced it.
 *
 * The window is deliberately short. An hour-long lockout would let a stranger
 * who sends three registrations block a real user's activation for that hour —
 * the throttle would suppress the owner's own resend. A minute between sends
 * still defeats a mail-bomb while costing a legitimate user almost nothing.
 */
final class VerificationMailer
{
    /** Minimum gap between two messages to the same address. */
    private const COOLDOWN_SECONDS = 60;

    /** Ceiling per address per hour, across every trigger. */
    private const HOURLY_CEILING = 6;

    public static function sendVerificationLink(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        return self::guard($user->getEmailForVerification(), function () use ($user): void {
            $user->sendEmailVerificationNotification();
        });
    }

    public static function sendAccountExistsNotice(User $user): bool
    {
        return self::guard($user->email, function () use ($user): void {
            Mail::to($user)->queue(new AccountExistsMail(
                recipientName: $user->name,
                loginUrl: FrontendUrl::to('/login'),
                resetUrl: FrontendUrl::to('/forgot-password'),
            ));
        });
    }

    /**
     * @param  callable(): void  $send
     * @return bool whether the message was actually dispatched
     */
    private static function guard(string $email, callable $send): bool
    {
        $address = sha1(mb_strtolower(trim($email)));
        $cooldown = 'signup-mail-cooldown:'.$address;
        $ceiling = 'signup-mail-hourly:'.$address;

        if (RateLimiter::tooManyAttempts($cooldown, 1)
            || RateLimiter::tooManyAttempts($ceiling, self::HOURLY_CEILING)) {
            return false;
        }

        RateLimiter::hit($cooldown, self::COOLDOWN_SECONDS);
        RateLimiter::hit($ceiling, 3600);

        $send();

        return true;
    }
}
