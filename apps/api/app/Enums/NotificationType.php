<?php

namespace App\Enums;

/**
 * What a member can be told about (W8-B). Each e-mail type has a switch in
 * the member's preferences (App\Models\NotificationPreference) and a signed
 * one-click unsubscribe link in every such e-mail (App\Support\Notifications\UnsubscribeToken).
 *
 * Defaults: the event-driven ones about the member's own content are on; the
 * monthly impact digest is off until the member turns it on. Review reminders
 * are not a switch: each one is an explicit request on a profile, and its
 * unsubscribe link cancels every pending reminder.
 */
enum NotificationType: string
{
    /**
     * Decisions about the member's reviews, topics, replies and reports. The
     * switch covers „received“, „published“ and report outcomes; a refusal
     * or removal (statement of reasons) is e-mailed regardless.
     */
    case Moderation = 'moderation';

    /** The doctor or facility replied publicly to the member's review. */
    case ReviewReply = 'review_reply';

    /** The member's review got new „Корисно“ votes (batched, at most daily). */
    case ReviewHelpful = 'review_helpful';

    /** Monthly „Ова се промените на кои придонесовте“ (opt-in). */
    case ImpactDigest = 'impact_digest';

    /** „Потсети ме за 2 недели“: one e-mail per request. */
    case ReviewReminder = 'review_reminder';

    /** In-app only: the one-time invitation to the monthly digest. */
    case DigestInvite = 'digest_invite';

    /**
     * The switches the preferences page shows, in order.
     *
     * @return list<self>
     */
    public static function preferenceTypes(): array
    {
        return [self::Moderation, self::ReviewReply, self::ReviewHelpful, self::ImpactDigest];
    }

    public function isPreference(): bool
    {
        return in_array($this, self::preferenceTypes(), true);
    }

    public function defaultEnabled(): bool
    {
        return $this !== self::ImpactDigest;
    }

    /** Types an unsubscribe link may carry. */
    public function isUnsubscribable(): bool
    {
        return $this->isPreference() || $this === self::ReviewReminder;
    }
}
