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
        'account_suspended' => 'Оваа сметка е привремено оневозможена. Ако мислите дека станува збор за грешка, обратете ни се.',
    ],

    'account' => [
        'deleted_user_name' => 'Избришан корисник',
        'unnamed_member' => 'Член',
        'password_incorrect' => 'Лозинката не е точна.',
        'staff_cannot_delete' => 'Сметките на тимот не можат да се избришат од тука. Обратете се до администратор.',
        'deleted' => 'Сметката е избришана.',
        'export_throttled' => 'Веќе преземавте копија неодамна. Обидете се повторно подоцна.',
        'token_revoked' => 'Уредот е одјавен.',
        'tokens_revoked' => 'Другите уреди се одјавени.',
    ],

    'registration' => [
        'disabled' => 'Регистрацијата е привремено оневозможена.',
    ],

    'username' => [
        // Community actions (reviews, forum, reports, „Корисно“) wait until a
        // member who still has a temporary clen-… name chooses one.
        'required' => 'Пред да објавувате, изберете корисничко име во вашата сметка.',
        'cooldown' => 'Корисничкото име може да се менува еднаш на 90 дена. Следна промена е можна од :date.',
        'renamed' => 'Корисничкото име е сменето.',
    ],

    'module' => [
        'unavailable' => 'Овој дел сè уште не е достапен.',
    ],

    'maintenance' => [
        'active' => 'Сервисот е привремено недостапен поради одржување.',
    ],

    'forum' => [
        'topic_locked' => 'Темата е заклучена и не прима нови одговори.',
        'tag_invalid' => 'Секој клучен збор треба да има 2–40 букви или цифри и не смее да биде само број.',
    ],

    'review' => [
        'duplicate' => 'Веќе сте испратиле рецензија за овој профил.',
        'helpful_own' => 'Не можете да ја означите вашата рецензија како корисна.',
        'helpful_own_profile' => 'Рецензиите за профилот што го уредувате не можете да ги означувате како корисни.',
        'aspect_unknown' => 'Една од дополнителните оценки не постои за овој профил.',
        'own_profile' => 'Не можете да оставите рецензија за профилот што го уредувате.',
        'resubmission_used' => 'Рецензијата веќе еднаш ја изменивте и ја испративте повторно, но ни тогаш не беше објавена. Оваа одлука е конечна.',
    ],

    'profile_correction' => [
        'received_correction' => 'Ви благодариме. Пријавата е примена. Тимот ќе ја провери и ќе го исправи профилот ако податокот е погрешен, во рок од 15 дена.',
        'received_objection' => 'Барањето е примено. Тимот ќе ве контактира за да потврди дека профилот е ваш и ќе ви одговори со образложение најдоцна за 30 дена.',
    ],

    'profile_report' => [
        'received' => 'Ви благодариме. Пријавата е примена и тимот ќе го провери профилот.',
    ],

    'altcha' => [
        'failed' => 'Не успеавме да потврдиме дека формуларот го испраќа човек. Обидете се повторно.',
    ],

    'report' => [
        'received' => 'Ви благодариме. Пријавата е примена и ќе ја прегледа модератор.',
        'hidden_default_note' => 'Содржината е отстранета по пријава и преглед од модератор, бидејќи не е во согласност со правилата на заедницата.',
        'own_content' => 'Не можете да пријавите своја содржина.',
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

    // „Мој профил“: a doctor's own account (W5-C).
    'doctor_account' => [
        'not_linked' => 'Оваа сметка не е поврзана со профил на лекар.',
        'no_changes' => 'Нема промени во однос на тековниот профил.',
        'change_request_pending' => 'Веќе имате барање за промени што чека преглед. Почекајте одлука или повлечете го.',
        'change_request_received' => 'Барањето е испратено. Профилот ги задржува досегашните податоци додека тимот не го прегледа.',
        'reply_staff_exists' => 'Под оваа рецензија веќе има одговор внесен од тимот. За измена обратете ни се.',
        'reply_pending' => 'Одговорот е зачуван и ќе се објави откако ќе го прегледа тимот.',
        'reply_published' => 'Одговорот е објавен.',
        'office_hours_day' => 'Изберете ден од неделата.',
        'claim_received' => 'Барањето е испратено. Тимот ќе ве контактира за да потврди дека профилот е ваш.',
        'claim_taken' => 'Овој профил веќе го уредува друга сметка. Ако мислите дека е грешка, обратете ни се.',
        'claim_already_yours' => 'Веќе го уредувате овој профил.',
        'claim_already_manager' => 'Вашата сметка веќе уредува профил на лекар.',
        'claim_limit' => 'Имате повеќе отворени барања. Почекајте тимот да ги прегледа.',
        'fields' => [
            'full_name' => 'име и презиме',
            'title' => 'титула',
            'subspecialty' => 'супспецијалност',
            'education' => 'образование',
            'years_experience' => 'години искуство',
            'city' => 'град',
            'specialties' => 'специјалности',
            'facilities' => 'установи',
        ],
    ],

    // Public labels for `verification.basis_label` (VerificationBasis).
    'verification' => [
        'basis' => [
            'doctor' => [
                'official_registers' => 'Регистар на ФЗОМ и Лекарска комора',
                'licence_and_website' => 'Важечка лиценца и веб-страница на установата',
                'website_and_register' => 'Веб-страница на установата и регистар на ФЗОМ',
                'staff' => 'Потврдено од нашиот тим',
                'owner_claim' => 'Профилот го презеде самиот лекар (со потврден идентитет)',
            ],
            // A dentist verified by the ФЗОМ contract alone (no Комора licence).
            'dentist' => [
                'official_registers' => 'Регистар на ФЗОМ',
            ],
            'facility' => [
                'official_registers' => 'Регистар на ФЗОМ',
                'licence_and_website' => 'Веб-страница на установата и регистар на ФЗОМ',
                'website_and_register' => 'Веб-страница на установата и регистар на ФЗОМ',
                'staff' => 'Потврдено од нашиот тим',
                'owner_claim' => 'Профилот го презеде самата установа (со потврден идентитет)',
            ],
        ],
    ],

    // W8-B review reminders and notifications.
    'reminder' => [
        'own_profile' => 'Не можете да поставите потсетник за профилот што го уредувате.',
        'already_reviewed' => 'Веќе имате рецензија за овој профил.',
        'too_many' => 'Имате премногу активни потсетници. Откажете некој во поставките за известувања.',
    ],

    'notifications' => [
        'unsubscribe_invalid' => 'Линкот за одјава не е важечки. Поставките можете да ги смените во вашата сметка.',
    ],

];
