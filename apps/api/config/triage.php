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

    /*
     * Local draft preview: serve the latest version of every flow, draft or
     * reviewed, instead of only the published ones. Honoured only outside a
     * deployment (App\Support\DeploymentEnvironment); platform:preflight
     * fails a deployed environment that sets it.
     */
    'preview_drafts' => (bool) env('TRIAGE_PREVIEW_DRAFTS', false),

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

    /*
     * Demographic appropriateness (FlowLinter, docs/triage-flows.md §13).
     * Copy that names sex-, age- or pregnancy-specific things must not be
     * reachable for a visitor it does not fit. `stems` are lower-case
     * Macedonian word stems (substring match, so both cases and endings);
     * `forbidden` lists the demographics the copy must never be shown to:
     *   sex            - these sex answers (unspecified is never forbidden:
     *                    under-triage is worse, ask neutrally instead)
     *   age_years_lt   - younger than this many years
     *   age_years_gte  - this many years or older
     *   pregnancy      - these pregnancy answers (not_asked = pregnancy not
     *                    applicable: male, under 10 or over 55)
     * `min_age_years` limits a category to visitors of at least that age.
     * A node, outcome or red flag (or the whole flow, at the top level) may
     * carry "demographics_ok_reason" (internal English) for a justified case.
     */
    'demographic_keywords' => [
        'male_anatomy' => [
            'stems' => ['тестис', 'тестикул', 'скротум', 'мошниц', 'простат', 'пенис', 'ерекци', 'предкожиц', 'сперм'],
            'forbidden' => ['sex' => ['female']],
        ],
        'female_anatomy' => [
            'stems' => ['вагин', 'влагалиш', 'матка', 'јајник'],
            'forbidden' => ['sex' => ['male']],
        ],
        'menstruation' => [
            'stems' => ['менструа', 'циклус', 'мензис'],
            'forbidden' => ['sex' => ['male'], 'age_years_lt' => 8],
        ],
        'menopause' => [
            'stems' => ['менопауз'],
            'forbidden' => ['sex' => ['male'], 'age_years_lt' => 40],
        ],
        'pregnancy' => [
            'stems' => ['бремен', 'трудн', 'породув', 'постпартум', 'лохии', 'плодот'],
            'forbidden' => ['sex' => ['male'], 'pregnancy' => ['not_asked']],
        ],
        'breastfeeding' => [
            // A carer answers for a baby or young child: feeding the baby is about the carer.
            'stems' => ['доење', 'дојење', 'доите', 'доиле'],
            'min_age_years' => 13,
            'forbidden' => ['sex' => ['male'], 'pregnancy' => ['not_asked']],
        ],
        'infant_care' => [
            'stems' => ['фонтанел', 'пелен', 'доенч', 'новороденч', 'цица'],
            'forbidden' => ['age_years_gte' => 5],
        ],
        'adult_activities' => [
            'stems' => ['алкохол', 'возење', 'возач', 'возил'],
            'forbidden' => ['age_years_lt' => 13],
        ],
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
