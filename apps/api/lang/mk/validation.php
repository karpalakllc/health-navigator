<?php

/*
|--------------------------------------------------------------------------
| Validation messages (Macedonian)
|--------------------------------------------------------------------------
|
| Scoped to the rules this API actually uses (see app/Http/Requests/Api/V1).
| Anything not translated here falls back to English via APP_FALLBACK_LOCALE,
| which is deliberate: a partial, reviewed translation is safer than a full
| machine-generated one on a health platform.
|
| NOT natively reviewed — see the note in lang/mk/api.php.
|
*/

return [

    'accepted' => 'Полето :attribute мора да биде прифатено.',
    'array' => 'Полето :attribute мора да биде листа.',
    'boolean' => 'Полето :attribute мора да биде точно или неточно.',
    'confirmed' => 'Потврдата на полето :attribute не се совпаѓа.',
    'email' => 'Полето :attribute мора да биде валидна е-адреса.',
    'image' => 'Полето :attribute мора да биде слика.',
    'in' => 'Избраната вредност за :attribute е невалидна.',
    'integer' => 'Полето :attribute мора да биде цел број.',
    'required' => 'Полето :attribute е задолжително.',
    'string' => 'Полето :attribute мора да биде текст.',
    'unique' => 'Оваа вредност за :attribute е веќе зафатена.',

    'between' => [
        'array' => 'Полето :attribute мора да има помеѓу :min и :max ставки.',
        'file' => 'Полето :attribute мора да биде помеѓу :min и :max килобајти.',
        'numeric' => 'Полето :attribute мора да биде помеѓу :min и :max.',
        'string' => 'Полето :attribute мора да има помеѓу :min и :max знаци.',
    ],

    'max' => [
        'array' => 'Полето :attribute не смее да има повеќе од :max ставки.',
        'file' => 'Полето :attribute не смее да биде поголемо од :max килобајти.',
        'numeric' => 'Полето :attribute не смее да биде поголемо од :max.',
        'string' => 'Полето :attribute не смее да биде подолго од :max знаци.',
    ],

    'min' => [
        'array' => 'Полето :attribute мора да има најмалку :min ставки.',
        'file' => 'Полето :attribute мора да биде најмалку :min килобајти.',
        'numeric' => 'Полето :attribute мора да биде најмалку :min.',
        'string' => 'Полето :attribute мора да има најмалку :min знаци.',
    ],

    'attributes' => [
        'accepted_community_rules' => 'правилата на заедницата',
        'body' => 'содржина',
        'email' => 'е-адреса',
        'name' => 'име',
        'password' => 'лозинка',
        'password_confirmation' => 'потврда на лозинката',
        'rating' => 'оценка',
        'title' => 'наслов',
        'avatar' => 'профилна слика',
    ],

];
