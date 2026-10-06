<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Directory imports (ФЗОМ, Лекарска комора, institution websites)
    |--------------------------------------------------------------------------
    |
    | Raw source snapshots and diff summaries go to a PRIVATE disk (never the
    | public media disk): they hold names of people and internal keys.
    | Only the newest `snapshot_retention` snapshots per source are kept.
    |
    */

    'disk' => env('IMPORT_DISK', 'local'),

    'directory' => env('IMPORT_DIRECTORY', 'imports'),

    'snapshot_retention' => (int) env('IMPORT_SNAPSHOT_RETENTION', 3),

    /*
    | Every request to a source identifies us. Set IMPORT_CONTACT to a
    | monitored address before the first real run.
    */
    'user_agent' => env('IMPORT_USER_AGENT', 'Zdravje360-DirectoryImport/1.0'),

    'contact' => env('IMPORT_CONTACT', 'contact@example.invalid'),

    // Pause between two requests to the same host, in seconds.
    'request_delay_seconds' => (float) env('IMPORT_REQUEST_DELAY', 5),

    'timeout_seconds' => (int) env('IMPORT_TIMEOUT', 60),

    // Largest file accepted from a source; ФЗОМ's are ~6 MB.
    'max_download_bytes' => (int) env('IMPORT_MAX_DOWNLOAD_BYTES', 50 * 1024 * 1024),

    // Records written per database transaction.
    'batch_size' => (int) env('IMPORT_BATCH_SIZE', 250),

    /*
    | A record absent from this many consecutive complete runs goes to the
    | review queue as "missing". Nothing is ever deleted or unpublished
    | automatically.
    */
    'missing_after_runs' => (int) env('IMPORT_MISSING_AFTER_RUNS', 2),

    /*
    | Safety stop: an apply run that would mark more than this share of the
    | previously seen records missing aborts before writing anything (a
    | truncated or half-generated source file looks exactly like that).
    */
    'max_missing_ratio' => (float) env('IMPORT_MAX_MISSING_RATIO', 0.2),

    'fzom' => [
        'files' => [
            'pzz' => env('IMPORT_FZOM_PZZ_URL', 'https://arhiva.fzo.org.mk/XML/LekariLista_SitePzz.xml'),
            'spec' => env('IMPORT_FZOM_SPEC_URL', 'https://arhiva.fzo.org.mk/XML/LekariLista_SiteSpec.xml'),
        ],

        // TipDogovorID values whose rows are never imported: pharmacies
        // („Аптеки“) — pharmacists are not doctors, and the pharmacy
        // vertical has its own sources.
        'excluded_contract_types' => [4],

        // TipDogovorID values that are hospital care (facility type hospital).
        'hospital_contract_types' => [16, 17, 18, 19, 20, 21, 22, 23, 24, 34, 35, 36],

        // TipDogovorID values that are laboratory services.
        'laboratory_contract_types' => [9, 10],

        // Primary care contracts: a doctor's primary workplace when present.
        'primary_care_contract_types' => [1, 2, 3],
    ],

    'website' => [
        // Images larger than this are skipped (the research brief caps at 2 MB).
        'max_image_bytes' => (int) env('IMPORT_WEBSITE_MAX_IMAGE_BYTES', 2 * 1024 * 1024),
    ],

];
