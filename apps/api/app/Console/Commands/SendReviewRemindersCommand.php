<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Mail\ReviewReminderMail;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Review;
use App\Models\ReviewReminder;
use App\Models\User;
use App\Support\FrontendUrl;
use App\Support\Notifications\MemberNotifier;
use App\Support\Notifications\ProfileRef;
use Illuminate\Console\Command;

/**
 * W8-B „Потсети ме за 2 недели“: sends each due reminder once and deletes
 * it. A reminder that no longer makes sense — the member already reviewed
 * the profile, the profile is gone or hidden, the account was deleted — is
 * deleted without a message.
 */
class SendReviewRemindersCommand extends Command
{
    protected $signature = 'reviews:send-reminders';

    protected $description = 'Send the review reminders members asked for, then delete them';

    public function handle(): int
    {
        $sent = 0;
        $dropped = 0;

        ReviewReminder::query()
            ->where('remind_at', '<=', now())
            ->with(['user', 'reviewable'])
            ->chunkById(200, function ($reminders) use (&$sent, &$dropped): void {
                foreach ($reminders as $reminder) {
                    /** @var ReviewReminder $reminder */
                    $user = $reminder->user;
                    $profile = $reminder->reviewable;
                    $reminder->delete();

                    if (! $user instanceof User || ! $this->stillUseful($user, $profile)) {
                        $dropped++;

                        continue;
                    }

                    $ref = ProfileRef::for($profile);

                    if ($ref === null) {
                        $dropped++;

                        continue;
                    }

                    MemberNotifier::send($user, NotificationType::ReviewReminder, [
                        'profile' => $ref,
                    ], new ReviewReminderMail(
                        recipientName: $user->name,
                        profileName: $ref['name'],
                        isDoctor: $ref['kind'] === 'doctor',
                        actionUrl: FrontendUrl::to($ref['path'].'#review-form'),
                    ));

                    $sent++;
                }
            });

        $this->info("Sent {$sent} reminder(s), dropped {$dropped}.");

        return self::SUCCESS;
    }

    private function stillUseful(User $user, mixed $profile): bool
    {
        if ($user->isAnonymised() || $user->isSuspended()) {
            return false;
        }

        $visible = match (true) {
            $profile instanceof Doctor => $profile->is_published && ! $profile->isOwnedBy($user),
            $profile instanceof Facility => (bool) $profile->is_published,
            default => false,
        };

        return $visible && ! Review::query()
            ->where('user_id', $user->getKey())
            ->where('reviewable_type', $profile::class)
            ->where('reviewable_id', $profile->getKey())
            ->exists();
    }
}
