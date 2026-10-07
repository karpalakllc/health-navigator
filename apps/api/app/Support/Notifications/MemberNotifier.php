<?php

namespace App\Support\Notifications;

use App\Enums\NotificationType;
use App\Mail\Concerns\Unsubscribable;
use App\Models\MemberNotification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * The one way member notifications go out (W8-B): a line in the member's
 * „Известувања“ and, when their settings allow it, the e-mail with its
 * signed unsubscribe link. A deleted account gets neither.
 *
 * The in-app line is kept whatever the e-mail settings say, so a member who
 * turned e-mails off still finds a moderation decision in their account. A
 * refusal or removal (the DSA statement of reasons, docs/notice-and-action.md)
 * is e-mailed even then: it is not a preference.
 */
final class MemberNotifier
{
    /**
     * @param  array<string, mixed>|null  $data  the in-app line; null sends the e-mail only
     * @param  bool  $always  a statement of reasons (a refusal or removal):
     *                        e-mailed whatever the settings say, without an unsubscribe link
     */
    public static function send(User $user, NotificationType $type, ?array $data, ?Mailable $mail = null, bool $always = false): bool
    {
        if ($user->isAnonymised()) {
            return false;
        }

        if ($data !== null) {
            MemberNotification::query()->create([
                'user_id' => $user->getKey(),
                'type' => $type,
                'data' => $data,
            ]);
        }

        if ($mail === null || (! $always && ! NotificationPreference::for($user)->allowsEmail($type))) {
            return false;
        }

        if ($mail instanceof Unsubscribable && ! $always) {
            $mail->withUnsubscribe($user, $type);
        }

        Mail::to($user)->queue($mail);

        return true;
    }

    /**
     * The one-time in-app invitation to the monthly digest, the first time
     * one of the member's reviews is published. Nothing is e-mailed: the
     * digest is opt-in.
     */
    public static function inviteToDigest(User $user): void
    {
        if ($user->isAnonymised()) {
            return;
        }

        $preferences = NotificationPreference::for($user);

        if ($preferences->impact_digest || $preferences->digest_invited_at !== null) {
            return;
        }

        $preferences->digest_invited_at = now();
        $preferences->save();

        self::send($user, NotificationType::DigestInvite, []);
    }
}
