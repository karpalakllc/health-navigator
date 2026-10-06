<?php

/*
|--------------------------------------------------------------------------
| Directory data operations (W6-C, docs/data-import.md)
|--------------------------------------------------------------------------
|
| Scheduling and alerting around the source imports. The import commands
| themselves (`import:fzom`, `import:komora-licences`) and their fetch
| settings live with the import code; this file only decides when the
| scheduler runs them and who hears about a bad run.
|
*/

return [

    /*
    | Scheduled imports are OFF until the owner turns them on. Each entry is
    | also skipped (with a log warning) while its command is not installed,
    | so the schedule is safe on a build without the import code.
    |
    | ФЗОМ regenerates its XML daily; a weekly conditional GET is enough.
    | The Лекарска комора list changes about every four months; checking
    | monthly catches a new list within weeks.
    */
    'schedule' => [
        'fzom' => [
            'enabled' => filter_var(env('IMPORT_FZOM_SCHEDULE', false), FILTER_VALIDATE_BOOLEAN),
            'command' => 'import:fzom',
        ],
        'komora' => [
            'enabled' => filter_var(env('IMPORT_KOMORA_SCHEDULE', false), FILTER_VALIDATE_BOOLEAN),
            'command' => 'import:komora-licences',
        ],
    ],

    /*
    | Who is mailed when a scheduled import fails or a finished run changes
    | an unusual share of the records it saw. Unset falls back to the
    | operational alert inbox (PLATFORM_ALERT_EMAIL); with neither, nothing
    | is sent. A run counts as a large diff when the profiles it created,
    | changed or found missing are at least `large_diff_ratio` of the records
    | it saw AND at least `large_diff_min` in number (so a tiny test file
    | does not page anyone).
    */
    'alerts' => [
        'email' => env('IMPORT_ALERT_EMAIL'),
        'large_diff_ratio' => (float) env('IMPORT_LARGE_DIFF_RATIO', 0.10),
        'large_diff_min' => (int) env('IMPORT_LARGE_DIFF_MIN', 25),
        'throttle_minutes' => (int) env('IMPORT_ALERT_THROTTLE_MINUTES', 60),
    ],

];
