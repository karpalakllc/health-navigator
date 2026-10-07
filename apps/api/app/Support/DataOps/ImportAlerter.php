<?php

namespace App\Support\DataOps;

use App\Mail\ImportAlertMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mails the import alert inbox (config/data_ops.php) about a failed or
 * unusually large source import, or a register too old to verify from.
 *
 * - Sent synchronously: the scheduler's failure hook may run while the queue
 *   worker is the very thing that is broken.
 * - Throttled per source and kind (`alerts.throttle_minutes`), so a retry
 *   loop does not answer each attempt with a mail.
 * - Never throws: a failed alert is logged and the caller carries on.
 * - Counts and a truncated error line only, with e-mail addresses masked.
 */
class ImportAlerter
{
    public const KIND_FAILED = 'failed';

    public const KIND_LARGE_DIFF = 'large_diff';

    /** The verification engine found the ФЗОМ register older than its maximum age. */
    public const KIND_STALE_REGISTER = 'stale_register';

    private const ERROR_LIMIT = 300;

    /**
     * @param  array<string, int>  $counts
     */
    public function send(string $source, string $kind, array $counts = [], ?string $error = null, ?string $reviewUrl = null): bool
    {
        $recipient = $this->recipient();

        if ($recipient === null) {
            return false;
        }

        $minutes = max(1, (int) config('data_ops.alerts.throttle_minutes', 60));

        if (! Cache::add('alerts:import:'.$source.':'.$kind, true, now()->addMinutes($minutes))) {
            return false;
        }

        try {
            Mail::to($recipient)->send(new ImportAlertMail(
                sourceLabel: self::sourceLabel($source),
                kind: $kind,
                counts: $counts,
                error: $error === null ? null : self::clean($error),
                reviewUrl: $reviewUrl,
            ));
        } catch (Throwable $e) {
            // Not sent: the next alert must not be throttled away.
            Cache::forget('alerts:import:'.$source.':'.$kind);
            Log::warning('Could not send the import alert.', [
                'source' => $source,
                'kind' => $kind,
                'alert_error' => $e::class,
            ]);

            return false;
        }

        return true;
    }

    /** The scheduler's hook when a scheduled import command exits non-zero. */
    public function scheduledRunFailed(string $source, string $command): void
    {
        $this->send($source, self::KIND_FAILED, error: "Закажаната команда `{$command}` заврши со грешка. Видете го дневникот на серверот.");
    }

    public static function sourceLabel(string $source): string
    {
        return match ($source) {
            'fzom' => 'ФЗОМ — Шифрарник на лекари',
            'komora' => 'Лекарска комора — листа на важечки лиценци',
            default => $source,
        };
    }

    private function recipient(): ?string
    {
        foreach ([config('data_ops.alerts.email'), config('zdravje.alerts.email')] as $address) {
            if (is_string($address) && trim($address) !== '') {
                return trim($address);
            }
        }

        return null;
    }

    private static function clean(string $error): string
    {
        return Str::of($error)
            ->before("\n")
            ->replaceMatches('/[^\s<>"\'(),;:]+@[^\s<>"\'(),;:]+/u', '[email]')
            ->limit(self::ERROR_LIMIT)
            ->toString();
    }
}
