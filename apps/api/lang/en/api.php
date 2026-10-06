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
        'registration_pending' => 'If the address can be used, we have sent a message with the next step. Please check your inbox.',
        'privacy_note' => 'For security, we do not reveal whether an address is already registered.',
        'throttled' => 'Too many failed attempts. Please try again in :seconds seconds.',
        'email_unverified' => 'Please confirm your email address before continuing. Check your inbox or request a new link.',
        'staff_use_admin' => 'This account is protected by two-factor authentication. Sign in through the administration panel.',
        'account_suspended' => 'This account is temporarily disabled. If you think this is a mistake, please contact us.',
    ],

    'account' => [
        'deleted_user_name' => 'Deleted user',
        'password_incorrect' => 'The password is incorrect.',
        'staff_cannot_delete' => 'Team accounts cannot be deleted here. Please contact an administrator.',
        'deleted' => 'The account has been deleted.',
        'export_throttled' => 'You downloaded a copy recently. Please try again later.',
        'token_revoked' => 'The device has been signed out.',
        'tokens_revoked' => 'Other devices have been signed out.',
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
        'helpful_own' => 'You cannot mark your own review as helpful.',
        'own_profile' => 'You cannot review the profile you manage.',
    ],

    'report' => [
        'received' => 'Thank you. The report has been received and a moderator will review it.',
        'hidden_default_note' => 'The content was removed after a report and a moderator review because it does not follow the community rules.',
        'own_content' => 'You cannot report your own content.',
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

    // „Мој профил“: a doctor's own account (W5-C).
    'doctor_account' => [
        'not_linked' => 'This account is not linked to a doctor profile.',
        'no_changes' => 'Nothing differs from the current profile.',
        'change_request_pending' => 'You already have a change request waiting for review. Wait for the decision or withdraw it.',
        'change_request_received' => 'Request sent. The profile keeps its current details until the team reviews it.',
        'reply_staff_exists' => 'This review already has a response entered by the team. Contact us to change it.',
        'reply_pending' => 'Reply saved. It will be published once the team has reviewed it.',
        'reply_published' => 'Reply published.',
        'office_hours_day' => 'Choose a day of the week.',
        'claim_received' => 'Request sent. The team will contact you to confirm the profile is yours.',
        'claim_taken' => 'Another account already manages this profile. If you think this is a mistake, contact us.',
        'claim_already_yours' => 'You already manage this profile.',
        'claim_already_manager' => 'Your account already manages a doctor profile.',
        'claim_limit' => 'You have several open requests. Wait for the team to review them.',
        'fields' => [
            'full_name' => 'full name',
            'title' => 'title',
            'subspecialty' => 'subspecialty',
            'education' => 'education',
            'years_experience' => 'years of experience',
            'city' => 'city',
            'specialties' => 'specialties',
            'facilities' => 'workplaces',
        ],
    ],

];
