<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Mail\ImpactDigestMail;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\FrontendUrl;
use App\Support\Levels\LevelRules;
use App\Support\Notifications\ImpactStats;
use App\Support\Notifications\MemberNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * W8-B: the opt-in monthly „Ова се промените на кои придонесовте“ for the
 * previous calendar month in Macedonian time. Only members who turned it on
 * and kept e-mail on; nothing is sent for a month without any change.
 *
 * Idempotent: each member's row records the last month handled
 * (`impact_digest_month`), claimed before the e-mail is queued, so a rerun,
 * a crash and rerun or a manual --month never send a month twice. It is
 * scheduled daily: a machine that was off on the 1st catches the previous
 * month up once, on its next run; an older month is never sent after a
 * newer one.
 */
class SendImpactDigestCommand extends Command
{
    protected $signature = 'notifications:send-impact-digest {--month= : YYYY-MM, default the previous month}';

    protected $description = 'Send the monthly impact digest to members who opted in';

    private const MONTHS = [
        1 => 'јануари', 2 => 'февруари', 3 => 'март', 4 => 'април', 5 => 'мај', 6 => 'јуни',
        7 => 'јули', 8 => 'август', 9 => 'септември', 10 => 'октомври', 11 => 'ноември', 12 => 'декември',
    ];

    public function handle(): int
    {
        $option = $this->option('month');
        $month = is_string($option) && preg_match('/^\d{4}-\d{2}$/', $option) === 1
            ? Carbon::createFromFormat('!Y-m', $option, LevelRules::TIMEZONE)
            : now(LevelRules::TIMEZONE)->subMonthNoOverflow()->startOfMonth();

        if (! $month instanceof Carbon) {
            $this->error('Invalid --month.');

            return self::FAILURE;
        }

        $key = $month->format('Y-m');
        $label = self::MONTHS[$month->month].' '.$month->year;
        $sent = 0;
        $notHandled = fn ($query) => $query->whereNull('impact_digest_month')->orWhere('impact_digest_month', '<', $key);

        NotificationPreference::query()
            ->where('impact_digest', true)
            ->where('email_enabled', true)
            ->where($notHandled)
            ->with('user')
            ->chunkById(200, function ($rows) use ($month, $key, $label, $notHandled, &$sent): void {
                foreach ($rows as $row) {
                    /** @var NotificationPreference $row */
                    $user = $row->user;

                    if (! $user instanceof User || $user->isAnonymised() || $user->isSuspended()) {
                        continue;
                    }

                    // Claim the month first: a second run (or a parallel one)
                    // finds it taken and sends nothing.
                    $claimed = NotificationPreference::query()->whereKey($row->getKey())->where($notHandled)
                        ->update(['impact_digest_month' => $key]);

                    if ($claimed !== 1) {
                        continue;
                    }

                    $stats = ImpactStats::forMonth($user, $month);

                    if (ImpactStats::isEmpty($stats)) {
                        continue;
                    }

                    MemberNotifier::send($user, NotificationType::ImpactDigest, [
                        'month' => $key,
                        'stats' => $stats,
                    ], new ImpactDigestMail(
                        recipientName: $user->name,
                        monthLabel: $label,
                        stats: $stats,
                        actionUrl: FrontendUrl::to('/account/reviews'),
                    ));

                    $sent++;
                }
            }, 'user_id');

        $this->info("Sent {$sent} digest(s) for {$label}.");

        return self::SUCCESS;
    }
}
