<?php

namespace App\Console\Commands;

use App\Mail\ModerationDigestMail;
use App\Services\ModerationDigestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendModerationDigestCommand extends Command
{
    protected $signature = 'moderation:send-digest {--dry-run : List recipients and counts without sending}';

    protected $description = 'Email staff and community moderators a daily summary of pending moderation queues';

    public function handle(ModerationDigestService $digest): int
    {
        $sent = 0;

        foreach ($digest->recipients() as $user) {
            $total = $digest->totalPending($user);

            if ($total === 0) {
                continue;
            }

            $queues = array_values(array_filter(
                $digest->queueLinksFor($user),
                fn (array $queue): bool => $queue['count'] > 0,
            ));

            if ($queues === []) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("{$user->email}: {$total} pending — ".collect($queues)->pluck('label')->join(', '));

                continue;
            }

            Mail::to($user)->queue(new ModerationDigestMail(
                recipientName: $user->name,
                totalPending: $total,
                queues: $queues,
                adminUrl: URL::to('/admin'),
            ));

            $sent++;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete.');

            return self::SUCCESS;
        }

        $this->info("Queued {$sent} moderation digest(s).");

        return self::SUCCESS;
    }
}
