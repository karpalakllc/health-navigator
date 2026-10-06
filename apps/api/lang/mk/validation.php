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
    'present' => 'Полето :attribute мора да биде испратено.',
    'regex' => 'Форматот на полето :attribute е невалиден.',
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
        // Spelled out rather than :attribute: the attribute's gender drove the
        // agreement („Оваа :attribute“), and only the password uses this rule.
        'uncompromised' => 'Оваа лозинка се појавила меѓу податоци протечени од други сервиси. Изберете друга лозинка.',
    ],

    'custom' => [
        // Consent checkboxes: „Полето правилата на заедницата мора да биде
        // прифатено“ does not read as a sentence, so these say what to do.
        'accepted_community_rules' => [
            'required' => 'Потврдете дека ги прифаќате правилата на заедницата.',
            'accepted' => 'Потврдете дека ги прифаќате правилата на заедницата.',
        ],
        'accepted_terms' => [
            'required' => 'Потврдете дека разбирате дека насоките се само општи информации.',
            'accepted' => 'Потврдете дека разбирате дека насоките се само општи информации.',
        ],
        // Never say which list a refused username matched (UsernameValidator).
        'username' => [
            'length' => 'Корисничкото име мора да има од 3 до 30 знаци.',
            'alphabet' => 'Корисничкото име може да содржи само букви (латиница или македонска кирилица), цифри и знаците . _ -',
            'start' => 'Корисничкото име мора да почнува со буква.',
            'separators' => 'Знаците . _ - не смеат да стојат еден до друг.',
            'mixed_script' => 'Користете или само латиница или само кирилица.',
            'not_allowed' => 'Ова корисничко име не е дозволено.',
            'taken' => 'Ова корисничко име веќе се користи.',
        ],
        'accept_terms' => [
            'required' => 'Потврдете дека имате најмалку 14 години и дека ги прифаќате Условите за користење и Политиката за приватност.',
            'accepted' => 'Потврдете дека имате најмалку 14 години и дека ги прифаќате Условите за користење и Политиката за приватност.',
        ],
    ],

    'attributes' => [
        'accepted_community_rules' => 'правилата на заедницата',
        'body' => 'содржина',
        'username' => 'корисничко име',
        'accept_terms' => 'условите за користење',
        'email' => 'е-адреса',
        'name' => 'име',
        'note' => 'белешка',
        'password' => 'лозинка',
        'password_confirmation' => 'потврда на лозинката',
        'rating' => 'оценка',
        'reason' => 'причина',
        'title' => 'наслов',
        'avatar' => 'профилна слика',
        'accepted_terms' => 'условите',
        'answers.*.values' => 'одговор',
        'q' => 'пребарување',
        'city' => 'град',
        'message' => 'порака',
        'contact' => 'контакт',
        'field' => 'податок',
        'type' => 'вид на барање',
    ],

];
