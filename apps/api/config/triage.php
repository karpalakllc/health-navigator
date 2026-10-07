<?php

/*
 * Symptom guidance v2 (docs/triage-flows.md, docs/triage-safety.md).
 */
return [
    /* Flow and global screen files, loaded by `triage:import`. */
    'flows_path' => database_path('data/triage/flows'),
    'global_path' => database_path('data/triage/global'),

    /*
     * Flows that were live before clinician sign-off existed. The importer
     * publishes these without a review record so the guidance that worked
     * before keeps working; every other flow stays hidden until a staff
     * member records a clinician review in the admin panel.
     */
    'grandfathered_flows' => ['general'],

    /* The flow offered when the visitor finds no matching symptom. */
    'fallback_flow' => 'general',

    /* At most this many presenting symptoms per session. */
    'max_symptoms' => 3,

    /*
     * The AI escalation layer (3f-b). Off: the NullEscalation answers, and no
     * external call is ever made. A real driver would be bound in
     * AppServiceProvider when `enabled` is true.
     */
    'escalation' => [
        'enabled' => (bool) env('TRIAGE_ESCALATION_ENABLED', false),
        'driver' => env('TRIAGE_ESCALATION_DRIVER', 'null'),
    ],

    /* Source domains the linter accepts without a warning (public material only). */
    'public_source_domains' => [
        'nhs.uk',
        'nice.org.uk',
        'who.int',
        'cdc.gov',
        'zdravstvo.gov.mk',
        'iph.mk',
        'fzo.org.mk',
        'ecdc.europa.eu',
        'nhsinform.scot',
        'medlineplus.gov',
        'nih.gov',
        'unicef.org',
    ],
];
