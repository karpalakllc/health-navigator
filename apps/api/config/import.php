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
    | Every request to a source (ФЗОМ and the Лекарска комора) identifies us:
    | "<IMPORT_USER_AGENT> (+mailto:<IMPORT_CONTACT>)". IMPORT_USER_AGENT is
    | the product token only; IMPORT_CONTACT an e-mail address. Set it to a
    | monitored address before the first real run.
    */
    'user_agent' => env('IMPORT_USER_AGENT', 'Zdravje360-DirectoryImport/1.0'),

    'contact' => env('IMPORT_CONTACT', 'contact@example.invalid'),

    // Pause between two requests to the same host, in seconds.
    'request_delay_seconds' => (float) env('IMPORT_REQUEST_DELAY', 5),

    'timeout_seconds' => (int) env('IMPORT_TIMEOUT', 60),

    // Largest file accepted from a source; ФЗОМ's are ~6 MB.
    'max_download_bytes' => (int) env('IMPORT_MAX_DOWNLOAD_BYTES', 50 * 1024 * 1024),

    /*
    | Diff summary CSVs, closed review items and lifted suppressions are
    | deleted this many days later (import:prune, daily).
    */
    'retention_days' => (int) env('IMPORT_RETENTION_DAYS', 365),

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

    // Extra public CA certificates (PEM) the import fetchers trust in
    // addition to the system store: intermediates a source host fails to
    // send. Relative to apps/api. Shipped: the intermediate of
    // arhiva.fzo.org.mk (resources/tls/import-extra-ca.crt, with its
    // fingerprint and expiry). Empty = the system store only. Verification
    // is never turned off.
    'ca_bundle' => env('IMPORT_CA_BUNDLE', 'resources/tls/import-extra-ca.crt'),

    // The verification engine (import:adjudicate, docs/verification.md).
    'verification' => [
        // Re-evaluate every profile right after each successful import apply
        // (ФЗОМ, Комора, websites). The nightly run happens regardless.
        'after_import' => (bool) env('IMPORT_VERIFY_AFTER_IMPORT', true),

        // Publish the drafts a run newly verifies, without staff. Off: staff
        // publish them with „Објави ги сите верифицирани“ in Import review.
        'auto_publish' => (bool) env('IMPORT_AUTO_PUBLISH_VERIFIED', false),

        // Publish, still unverified, the drafts a run newly finds current in
        // ФЗОМ with no licence on the Комора list (reason fzom_no_licence)
        // and no open review item on them (owner's decision). Off: staff use
        // „Објави ги и неверифицираните од ФЗОМ“ in Import review.
        'auto_publish_fzom_unverified' => (bool) env('IMPORT_AUTO_PUBLISH_FZOM_UNVERIFIED', false),
    ],

];
