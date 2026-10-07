<?php

namespace App\Console\Commands;

use App\Support\Notifications\ReviewNotifications;
use Illuminate\Console\Command;

/**
 * W8-B: one batched „Корисно“ notice per review that gained votes since its
 * author was last told (scheduled daily).
 */
class SendReviewHelpfulNoticesCommand extends Command
{
    protected $signature = 'reviews:notify-helpful';

    protected $description = 'Tell review authors, in one batch, about new „Корисно“ votes';

    public function handle(): int
    {
        $sent = ReviewNotifications::sendHelpfulBatch();

        $this->info("Notified {$sent} review author(s).");

        return self::SUCCESS;
    }
}
