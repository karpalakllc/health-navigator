<?php

/*
 * „Дали ви помогна?“ and step drop-off counters (docs/urgent-care.md
 * § Feedback). Every value a visitor can send comes from a closed list or a
 * short slug pattern, so no free text can be stored. The reason lists must
 * match apps/web/src/lib/feedback.ts (FeedbackVocabularyParityTest).
 */
return [
    /*
     * Item keys: `<namespace>:<slug>[:<slug>[:<slug>]]`, lowercase. Namespaces:
     * guide (a /guides page), urgent-care (the finder, per city or overall),
     * guidance (symptom-guidance flows and outcomes), page (anything else).
     */
    'item_pattern' => '/^(guide|urgent-care|guidance|page):[a-z0-9][a-z0-9-]{0,47}(:[a-z0-9][a-z0-9_-]{0,47}){0,2}$/',

    /* Funnels: `guidance:<flow-slug>` (or another namespace above). */
    'funnel_pattern' => '/^(guidance|urgent-care|page):[a-z0-9][a-z0-9-]{0,47}(:[a-z0-9][a-z0-9_-]{0,47})?$/',

    /* A step id within a funnel: a node key, `start`, `outcome:<level>` … */
    'step_pattern' => '/^[a-z0-9][a-z0-9_.:-]{0,63}$/',

    'max_depth' => 200,

    'max_length' => 96,

    'reasons' => [
        'helpful' => ['clear', 'found-place', 'next-step'],
        'not_helpful' => ['unclear', 'not-found', 'wrong-info', 'outdated', 'not-relevant'],
    ],

    'max_reasons' => 3,

    /* Days the counters are kept (feedback:purge-old). */
    'retention_days' => (int) env('FEEDBACK_RETENTION_DAYS', 730),
];
