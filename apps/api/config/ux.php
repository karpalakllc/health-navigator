<?php

/*
 * Anonymous UX statistics (docs/ux-heatmaps.md): where visitors click and how
 * far they scroll, kept only as daily counters per page template.
 */
return [
    /*
     * Page templates the web tracker may report. Must match
     * apps/web/src/lib/ux/routes.ts (UxRoutesParityTest checks it). Account,
     * sign-in, admin and form pages are deliberately absent: they are never
     * tracked.
     */
    'routes' => [
        '/',
        '/about',
        '/disclaimer',
        '/privacy',
        '/terms',
        '/transparency',
        '/community',
        '/search',
        '/guidance',
        '/doctors',
        '/doctors/[slug]',
        '/facilities',
        '/facilities/[slug]',
        '/pharmacies',
        '/pharmacies/[slug]',
        '/products',
        '/products/[slug]',
        '/forum',
        '/forum/[categorySlug]',
        '/forum/[categorySlug]/[topicSlug]',
        '/forum/tags/[tag]',
        '/urgent-care',
        '/urgent-care/[city]',
        '/guides',
        '/guides/[slug]',
    ],

    /* Counters older than this many days are deleted by analytics:purge-old-events. */
    'retention_days' => (int) env('UX_RETENTION_DAYS', 180),

    /* How long a heatmap-overlay link minted in the admin panel stays valid. */
    'overlay_ttl_minutes' => (int) env('UX_OVERLAY_TTL_MINUTES', 120),
];
