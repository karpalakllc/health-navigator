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
| NOTE: these Macedonian strings were authored alongside the i18n plumbing and
| have NOT had a native review. See H7/H9 in the audit — Bulgarian spellings
| had previously reached production copy.
|
*/

return [

    'errors' => [
        'unauthenticated' => 'Не сте најавени.',
        'forbidden' => 'Немате дозвола за оваа акција.',
        'not_found' => 'Не е пронајдено.',
        'too_many_requests' => 'Премногу барања. Обидете се повторно подоцна.',
    ],

    'auth' => [
        'invalid_credentials' => 'Внесените податоци за најава се неточни.',
        'logged_out' => 'Одјавени сте.',
        'registration_pending' => 'Испративме порака со линк за потврда.',
        'privacy_note' => 'Од безбедносни причини не откриваме дали адресата е веќе регистрирана.',
        'throttled' => 'Премногу неуспешни обиди. Обидете се повторно за :seconds секунди.',
        'email_unverified' => 'Потврдете ја вашата е-адреса пред да продолжите. Проверете го сандачето или побарајте нов линк.',
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
        'fallback' => [
            'title' => 'Општи информации',
            'body' => 'Не можевме да ги вчитаме деталните насоки. Ако сте загрижени за вашето здравје, обратете се кај здравствен работник или повикајте ги службите за итна помош (194 / 112).',
            'home' => 'Почетна',
            'emergency' => 'Броеви за итни случаи',
        ],
    ],

];
