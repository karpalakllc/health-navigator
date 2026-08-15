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
| REVIEW STATUS: NOT natively reviewed. Authored alongside the i18n plumbing.
| Watch for Bulgarian spellings — see H7/H9 in the audit.
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

    /*
     * Messages for Password::defaults() (AppServiceProvider).
     *
     * Without this group these five fall back to English while :attribute is
     * still translated, producing half-Macedonian sentences on the sign-up and
     * password-reset forms — "The лозинка field must contain at least one
     * number." The attribute below was translated; the rule messages were not.
     */
    'password' => [
        'letters' => 'Полето :attribute мора да содржи барем една буква.',
        'mixed' => 'Полето :attribute мора да содржи барем една голема и една мала буква.',
        'numbers' => 'Полето :attribute мора да содржи барем една бројка.',
        'symbols' => 'Полето :attribute мора да содржи барем еден специјален знак.',
        'uncompromised' => 'Оваа :attribute се појавила во протекување на податоци. Изберете друга лозинка.',
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
