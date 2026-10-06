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
        'method_not_allowed' => 'This method is not allowed for this address.',
        'server_error' => 'Something went wrong on our side. Please try again later.',
    ],

    // `message` on a 422 is the first field error plus a count (lang/en.json);
    // this entry exists so the `validation.failed` code has a translation key.
    'validation' => [
        'failed' => 'The given data was invalid.',
        'invalid_encoding' => 'The value is not valid UTF-8 text.',
    ],

    'auth' => [
        'invalid_credentials' => 'The provided credentials are incorrect.',
        'logged_out' => 'Logged out.',
        'registration_pending' => 'We have sent a message with a confirmation link.',
        'privacy_note' => 'For security, we do not reveal whether an address is already registered.',
        'throttled' => 'Too many failed attempts. Please try again in :seconds seconds.',
        'email_unverified' => 'Please confirm your email address before continuing. Check your inbox or request a new link.',
        'staff_use_admin' => 'This account is protected by two-factor authentication. Sign in through the administration panel.',
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
        'invalid' => 'The image could not be read, or its dimensions are too large.',
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
        'red_flags_required' => 'Please answer the warning-signs question first.',
        'fallback' => [
            'title' => 'General information',
            'body' => 'We could not load detailed guidance. If you are worried about your health, contact a healthcare professional or emergency services (194 / 112).',
            'home' => 'Home',
            'emergency' => 'Emergency numbers',
        ],
    ],

];
