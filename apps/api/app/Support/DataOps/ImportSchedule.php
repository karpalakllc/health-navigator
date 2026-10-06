<?php

namespace App\Support\DataOps;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Whether a scheduled source import should run now (routes/console.php).
 * Off unless the owner enabled it in config/data_ops.php, and skipped with a
 * warning while the import command is not installed.
 */
final class ImportSchedule
{
    public static function command(string $source): string
    {
        return (string) config("data_ops.schedule.{$source}.command");
    }

    public static function shouldRun(string $source): bool
    {
        if (! filter_var(config("data_ops.schedule.{$source}.enabled", false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $command = self::command($source);

        if ($command === '' || ! array_key_exists($command, Artisan::all())) {
            Log::warning('Scheduled import skipped: the command is not installed.', [
                'source' => $source,
                'command' => $command,
            ]);

            return false;
        }

        return true;
    }
}
