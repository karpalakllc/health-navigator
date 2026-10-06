<?php

/*
| Only what the framework's English validation lines do not cover; Laravel
| merges this over them (lang/mk/validation.php is the full Macedonian set).
*/

return [

    'custom' => [
        'accepted_community_rules' => [
            'required' => 'Confirm that you accept the community rules.',
            'accepted' => 'Confirm that you accept the community rules.',
        ],
        'accepted_terms' => [
            'required' => 'Confirm that you understand the guidance is general information only.',
            'accepted' => 'Confirm that you understand the guidance is general information only.',
        ],
        'display_name' => [
            'regex' => 'The display name must start with a letter and may contain only letters, spaces and . - \'',
            'too_short' => 'The display name must contain at least two letters.',
            'mixed_script' => 'Do not mix Cyrillic and Latin letters within one word of the display name.',
            'reserved' => 'The display name cannot contain a title or role (e.g. "Dr", "administrator", "moderator", "team") or the platform\'s name.',
        ],
    ],

];
