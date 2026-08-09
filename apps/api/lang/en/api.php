<?php

/*
|--------------------------------------------------------------------------
| API messages (English)
|--------------------------------------------------------------------------
|
| Fallback locale. Keys double as the machine-readable `code` in the API
| error envelope — see lang/mk/api.php.
|
*/

return [

    'errors' => [
        'unauthenticated' => 'Unauthenticated.',
        'forbidden' => 'Forbidden.',
        'not_found' => 'Not found.',
        'too_many_requests' => 'Too many requests.',
    ],

    'auth' => [
        'invalid_credentials' => 'The provided credentials are incorrect.',
        'logged_out' => 'Logged out.',
    ],

    'registration' => [
        'disabled' => 'Registration is currently disabled.',
    ],

    'module' => [
        'unavailable' => 'This module is not available yet.',
    ],

    'maintenance' => [
        'active' => 'The service is temporarily unavailable for maintenance.',
    ],

    'forum' => [
        'topic_locked' => 'This topic is locked and does not accept new replies.',
    ],

    'review' => [
        'duplicate' => 'You have already submitted a review for this profile.',
    ],

    'avatar' => [
        'locked' => 'Profile photo upload is locked until you reach the required forum message count.',
    ],

    'guidance' => [
        'unavailable' => 'Symptom guidance is not available.',
        'no_flow' => 'No symptom guidance flow is published.',
        'terms_required' => 'You must accept the guidance terms before continuing.',
        'session_complete' => 'This guidance session is already complete.',
        'session_mismatch' => 'Session does not belong to the current flow.',
        'unknown_step' => 'Unknown step.',
        'invalid_option' => 'Invalid option selected.',
        'invalid_red_flag' => 'Invalid red-flag code.',
        'answer_required' => 'Please answer: :step',
        'fallback' => [
            'title' => 'General information',
            'body' => 'We could not load detailed guidance. If you are worried about your health, contact a healthcare professional or emergency services (194 / 112).',
            'home' => 'Home',
            'emergency' => 'Emergency numbers',
        ],
    ],

];
