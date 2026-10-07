<?php

/*
| The Лекарска комора licence list import (php artisan import:komora-licences).
|
| The list is published as a handful of PDFs on one page, refreshed about
| every four months. We fetch the page and its files politely: robots.txt is
| honoured, every request names us and a contact, requests are spaced out,
| and an unchanged file is not downloaded again (ETag / Last-Modified). The
| raw files are kept on the private disk for the last few runs only, for
| audit, and never in git or on public storage. The User-Agent and contact
| are the import's (config/import.php: IMPORT_USER_AGENT, IMPORT_CONTACT).
*/

return [
    'komora' => [
        'list_url' => env('KOMORA_LIST_URL', 'https://lkm.org.mk/mk/record/121/962/lista-na-doktori-so-vazhechki-licenci'),

        // Links to the list files on that page. Only https links on the
        // page's own host are followed (plus these extra hosts, if the
        // Комора ever moves the files to a CDN).
        'file_pattern' => '~/upload/records/962/[^"\'<>]+\.pdf~iu',
        'extra_hosts' => array_values(array_filter(explode(',', (string) env('KOMORA_EXTRA_HOSTS', '')))),

        'disk' => 'local',
        'directory' => 'imports/komora',

        // Raw files of this many most recent runs are kept; older are deleted.
        'keep_snapshots' => (int) env('KOMORA_KEEP_SNAPSHOTS', 3),

        // Pause between requests to the Комора's server.
        'request_delay_ms' => (int) env('KOMORA_REQUEST_DELAY_MS', 2000),
        'timeout' => 60,
        'max_bytes' => 20 * 1024 * 1024,
    ],

    'match' => [
        // Only profiles the ФЗОМ import created are candidates (doctors.import_source);
        // null considers every profile.
        'imported_source' => env('KOMORA_MATCH_IMPORTED_SOURCE', 'fzom'),

        // Drafts these imports created without a ФЗОМ record (the
        // institutions' own staff pages) are candidates too, but only for a
        // name no ФЗОМ profile carries: the ФЗОМ profile always comes first.
        // Empty: website-only drafts are never matched automatically.
        'fallback_sources' => array_values(array_filter(explode(',', (string) env('KOMORA_MATCH_FALLBACK_SOURCES', 'website')))),

        // Imported dentists sit under specialties with this slug prefix; they
        // have no licence on the Комора list.
        'dental_slug_prefix' => 'stomatologija',

        // The group of a doctor of medicine without a specialisation
        // („доктор на медицина во ПЗЗ“). Such a licence fits a profile with no
        // specialty at all: both say "no specialisation".
        'general_group' => env('KOMORA_GENERAL_GROUP', 'opsta-medicina'),
    ],
];
