<?php

/*
|--------------------------------------------------------------------------
| API messages (Macedonian)
|--------------------------------------------------------------------------
|
| Keys here double as the machine-readable `code` in the API error envelope,
| so a native client can localise independently of the message text. Keep the
| keys stable — they are part of the public contract (docs/api-contract.md).
|
| REVIEW STATUS: natively reviewed, corrections applied. The files that have
| NOT been reviewed are auth.php, passwords.php and validation.php — each says
| so in its own header rather than pointing here, because this note used to be
| the single source and went stale the moment this file was corrected.
|
| Watch for Bulgarian spellings specifically: see H7/H9 in the audit, where
| they had previously reached production copy.
|
*/

return [

    'errors' => [
        'unauthenticated' => 'Не сте најавени.',
        'forbidden' => 'Немате дозвола за оваа акција.',
        'not_found' => 'Не е пронајдено.',
        'too_many_requests' => 'Премногу барања. Обидете се повторно подоцна.',
        'method_not_allowed' => 'Методот не е дозволен за оваа адреса.',
        'server_error' => 'Настана грешка на серверот. Обидете се повторно подоцна.',
    ],

    // `message` on a 422 is the first field error plus a count (lang/mk.json);
    // this entry exists so the `validation.failed` code has a translation key.
    'validation' => [
        'failed' => 'Внесените податоци не се валидни.',
        'invalid_encoding' => 'Вредноста не е валиден UTF-8 текст.',
    ],

    'auth' => [
        'invalid_credentials' => 'Внесените податоци за најава се неточни.',
        'logged_out' => 'Одјавени сте.',
        // Conditional on purpose: an address that already has an account gets an
        // "already registered" notice instead of a link (N6).
        'registration_pending' => 'Ако адресата може да се користи, ви испративме порака со следниот чекор. Проверете го сандачето.',
        'privacy_note' => 'Од безбедносни причини не откриваме дали адресата е веќе регистрирана.',
        'throttled' => 'Премногу неуспешни обиди. Обидете се повторно за :seconds секунди.',
        'email_unverified' => 'Потврдете ја вашата е-адреса пред да продолжите. Проверете го сандачето или побарајте нов линк.',
        'staff_use_admin' => 'Оваа сметка е заштитена со двофакторска автентикација. Најавете се преку административниот панел.',
    ],

    'registration' => [
        'disabled' => 'Регистрацијата е привремено оневозможена.',
    ],

    'module' => [
        'unavailable' => 'Овој дел сè уште не е достапен.',
    ],

    'maintenance' => [
        'active' => 'Сервисот е привремено недостапен поради одржување.',
    ],

    'forum' => [
        'topic_locked' => 'Темата е заклучена и не прима нови одговори.',
    ],

    'review' => [
        'duplicate' => 'Веќе сте испратиле рецензија за овој профил.',
    ],

    'avatar' => [
        'locked' => 'Поставувањето профилна слика е достапно откако ќе го достигнете потребниот број пораки на форумот.',
        'invalid' => 'Сликата не може да се прочита или нејзините димензии се преголеми.',
    ],

    'guidance' => [
        'unavailable' => 'Насоките за симптоми не се достапни.',
        'no_flow' => 'Нема објавени насоки за симптоми.',
        'terms_required' => 'Мора да ги прифатите условите пред да продолжите.',
        'session_complete' => 'Оваа сесија со насоки е веќе завршена.',
        'session_mismatch' => 'Сесијата не припаѓа на тековните насоки.',
        'unknown_step' => 'Непознат чекор.',
        'invalid_option' => 'Избрана е невалидна опција.',
        'invalid_red_flag' => 'Невалиден код за предупредувачки знак.',
        'answer_required' => 'Ве молиме одговорете на: :step',
        'red_flags_required' => 'Ве молиме прво одговорете на прашањето за предупредувачки знаци.',
        'fallback' => [
            'title' => 'Општи информации',
            'body' => 'Не можевме да ги вчитаме деталните насоки. Ако сте загрижени за вашето здравје, обратете се кај здравствен работник или повикајте ги службите за итна помош (194 / 112).',
            'home' => 'Почетна',
            'emergency' => 'Броеви за итни случаи',
        ],
    ],

];
