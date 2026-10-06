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
        'username' => [
            'length' => 'The username must be 3 to 30 characters long.',
            'alphabet' => 'The username may contain only letters (Latin or Macedonian Cyrillic), digits and . _ -',
            'start' => 'The username must start with a letter.',
            'separators' => 'The characters . _ - cannot follow one another.',
            'mixed_script' => 'Use either Latin or Cyrillic letters, not both.',
            'not_allowed' => 'This username is not allowed.',
            'taken' => 'This username is already in use.',
        ],
        'accept_terms' => [
            'required' => 'Confirm that you are at least 14 and accept the Terms of Use and the Privacy Policy.',
            'accepted' => 'Confirm that you are at least 14 and accept the Terms of Use and the Privacy Policy.',
        ],
    ],

];
