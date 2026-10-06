# Macedonian copy — native review needed

**Please read this and tell me what to change.** You can reply in any form: mark up
this file, send me a list, or just say "row 12 in section A should be X".

## Round 1 feedback is applied

Three corrections landed: `не се точни` → `се неточни` (login error, in two
places that had drifted apart), and `Прикачувањето не успеа` →
`Прикачувањето е неуспешно`. The hedged confirmations were also shortened — the
screens now say what happened in one line, with a separate muted footnote
explaining that addresses are hidden deliberately. Rows below show the current
wording.

## What this sheet covers

The ~90 strings written or changed during the audit — API messages, validation,
emails. It does **not** cover the several hundred pre-existing interface strings
in the web app's `mk.ts`, which have never been through a native pass. Rows
24–28 are new: they are the password rules, which were missing entirely and were
rendering in English until recently.

## Why this exists

The audit found the emergency-call instruction written with a Bulgarian spelling —
**повика<u>й</u>те** (`й`, a letter Macedonian does not have) instead of
**повика<u>ј</u>те**. It appeared in **10 places**: the footer of every page, the
legal disclaimer, the symptom-guidance screen, the forum notice, the facility
emergency banner, and every welcome email. A second Bulgarian form,
**Добре дојдовте** instead of **Добредојдовте**, was in the welcome email subject
and body.

Both are fixed. But that told us the Macedonian copy had never been read by a
native speaker — and while fixing it I had to **write about 90 new Macedonian
strings**, because the API previously answered in a mix of English and Macedonian
and all of it had to move into proper translation files.

I can fix spelling. I cannot judge whether this reads naturally to a Macedonian
speaker, whether the tone is right for a health platform, or whether the formal
"вие" register is consistent. That is what I need from you.

## What to look for

1. **Anything that reads like a translation** rather than something a person would write.
2. **Tone.** This is a health platform — the register should be calm and plain, never
   alarming and never cute. Especially the emergency and guidance strings.
3. **Consistency of address.** I used the formal plural ("Проверете", "Најавете се")
   throughout. Confirm that is right, and that nothing slipped into singular.
4. **Terminology.** Is it *е-адреса* or *е-пошта*? *лозинка* everywhere? *сметка* or
   *профил* for an account? I picked one and used it consistently — tell me if I
   picked wrong.
5. **The safety-critical rows are marked in bold.** Those matter most.

---

## A. Site messages

Errors and notices the site shows in the page or as a message under a form.

| # | Macedonian | Intended meaning | Where it appears |
|---|------------|------------------|------------------|
| 1 | Не сте најавени. | Unauthenticated. | Any API call without a valid session |
| 2 | Немате дозвола за оваа акција. | Forbidden. | Action the signed-in user is not allowed to take |
| 3 | Не е пронајдено. | Not found. | Unknown doctor, facility, topic, etc. |
| 4 | Премногу барања. Обидете се повторно подоцна. | Too many requests. | Rate limit hit (e.g. repeated login attempts) |
| 5 | Внесените податоци за најава се неточни. | The provided credentials are incorrect. | Wrong email or password on the login form |
| 6 | Одјавени сте. | Logged out. | After signing out |
| 7 | Испративме порака со линк за потврда. | If that address can be used, we have sent a message with the next step. Please check your inbox. | **After submitting the signup form.** Worded to reveal nothing about whether the address already has an account |
| 8 | Потврдете ја вашата е-адреса пред да продолжите. Проверете го сандачето или побарајте нов линк. | Please confirm your email address before continuing. Check your inbox or request a new link. | Login with correct password but unconfirmed address; also when trying to post before confirming |
| 9 | Регистрацијата е привремено оневозможена. | Registration is currently disabled. | Signup form when registration is switched off in admin |
| 10 | Овој дел сè уште не е достапен. | This module is not available yet. | Visiting Pharmacies/Products/Forum/Guidance while that section is switched off |
| 11 | Сервисот е привремено недостапен поради одржување. | The service is temporarily unavailable for maintenance. | Every page while maintenance mode is on |
| 12 | Темата е заклучена и не прима нови одговори. | This topic is locked and does not accept new replies. | Replying to a locked forum thread |
| 13 | Веќе сте испратиле рецензија за овој профил. | You have already submitted a review for this profile. | Submitting a second review for the same doctor or facility |
| 14 | Поставувањето профилна слика е достапно откако ќе го достигнете потребниот број пораки на форумот. | Profile photo upload is locked until you reach the required forum message count. | Uploading a profile photo before reaching the required forum post count |
| 15 | Насоките за симптоми не се достапни. | Symptom guidance is not available. | Symptom guidance when no flow is published |
| 16 | Нема објавени насоки за симптоми. | No symptom guidance flow is published. | Same, internal variant |
| 17 | Мора да ги прифатите условите пред да продолжите. | You must accept the guidance terms before continuing. | Starting guidance without accepting the terms |
| 18 | Оваа сесија со насоки е веќе завршена. | This guidance session is already complete. | Continuing a guidance session that already finished |
| 19 | Сесијата не припаѓа на тековните насоки. | Session does not belong to the current flow. | Session belongs to an older version of the flow |
| 20 | Непознат чекор. | Unknown step. | Malformed guidance answer |
| 21 | Избрана е невалидна опција. | Invalid option selected. | Malformed guidance answer |
| 22 | Невалиден код за предупредувачки знак. | Invalid red-flag code. | Malformed emergency-symptom code |
| 23 | Ве молиме одговорете на: :step | Please answer: :step | Skipping a required guidance question. `:step` is the question label |
| 24 | Општи информации | General information | Guidance result when the configured outcome is missing |
| 25 | Не можевме да ги вчитаме деталните насоки. Ако сте загрижени за вашето здравје, обратете се кај здравствен работник или повикајте ги службите за итна помош (194 / 112). | We could not load detailed guidance. If you are worried about your health, contact a healthcare professional or emergency services (194 / 112). | **Safety-critical.** Shown if guidance content fails to load |
| 26 | Почетна | Home | Button on that fallback screen |
| 27 | Броеви за итни случаи | Emergency numbers | Button on that fallback screen |

## B. Sign-in messages

Shown on the login form.

| # | Macedonian | Intended meaning | Where it appears |
|---|------------|------------------|------------------|
| 1 | Внесените податоци за најава се неточни. |  | Login form, wrong credentials (framework key) |
| 2 | Внесената лозинка е неточна. |  | Wrong current password |
| 3 | Премногу обиди за најава. Обидете се повторно за :seconds секунди. |  | Too many login attempts. `:seconds` is a number |

## C. Password-reset messages

Shown on the forgot/reset password screens and in that email.

| # | Macedonian | Intended meaning | Where it appears |
|---|------------|------------------|------------------|
| 1 | Вашата лозинка е успешно променета. |  | After successfully setting a new password |
| 2 | Испративме линк за промена на лозинката. |  | **After requesting a password reset.** Worded to reveal nothing about whether the address exists |
| 3 | Ве молиме почекајте пред да се обидете повторно. |  | Requesting password resets too quickly |
| 4 | Линкот за промена на лозинката е невалиден или истечен. |  | Password-reset link expired or already used |
| 5 | Испративме линк за промена на лозинката. |  | Same wording as `sent`, deliberately — see above |

## D. Form validation messages

Shown under a form field when the entry is wrong. `:attribute` is replaced by the field name from section E; `:max`, `:min` by numbers.

| # | Macedonian | Intended meaning | Where it appears |
|---|------------|------------------|------------------|
| 1 | Полето :attribute мора да биде прифатено. |  |  |
| 2 | Полето :attribute мора да биде листа. |  |  |
| 3 | Полето :attribute мора да биде точно или неточно. |  |  |
| 4 | Потврдата на полето :attribute не се совпаѓа. |  |  |
| 5 | Полето :attribute мора да биде валидна е-адреса. |  |  |
| 6 | Полето :attribute мора да биде слика. |  |  |
| 7 | Избраната вредност за :attribute е невалидна. |  |  |
| 8 | Полето :attribute мора да биде цел број. |  |  |
| 9 | Полето :attribute е задолжително. |  |  |
| 10 | Полето :attribute мора да биде текст. |  |  |
| 11 | Оваа вредност за :attribute е веќе зафатена. |  |  |
| 12 | Полето :attribute мора да има помеѓу :min и :max ставки. |  |  |
| 13 | Полето :attribute мора да биде помеѓу :min и :max килобајти. |  |  |
| 14 | Полето :attribute мора да биде помеѓу :min и :max. |  |  |
| 15 | Полето :attribute мора да има помеѓу :min и :max знаци. |  |  |
| 16 | Полето :attribute не смее да има повеќе од :max ставки. |  |  |
| 17 | Полето :attribute не смее да биде поголемо од :max килобајти. |  |  |
| 18 | Полето :attribute не смее да биде поголемо од :max. |  |  |
| 19 | Полето :attribute не смее да биде подолго од :max знаци. |  |  |
| 20 | Полето :attribute мора да има најмалку :min ставки. |  |  |
| 21 | Полето :attribute мора да биде најмалку :min килобајти. |  |  |
| 22 | Полето :attribute мора да биде најмалку :min. |  |  |
| 23 | Полето :attribute мора да има најмалку :min знаци. |  |  |
| 24 | Полето :attribute мора да содржи барем една буква. |  | Password rule: must contain a letter |
| 25 | Полето :attribute мора да содржи барем една голема и една мала буква. |  | Password rule: must mix upper and lower case |
| 26 | Полето :attribute мора да содржи барем една бројка. |  | Password rule: must contain a digit |
| 27 | Полето :attribute мора да содржи барем еден специјален знак. |  | Password rule: must contain a symbol |
| 28 | Оваа :attribute се појавила во протекување на податоци. Изберете друга лозинка. |  | Shown when the chosen password appears in a known breach list. Note :attribute is feminine here (*лозинка*) — check the agreement reads naturally |

## E. Field names

Substituted into the messages in section D — e.g. “Полето **е-адреса** мора да биде валидна е-адреса.”

| # | Macedonian | Intended meaning | Where it appears |
|---|------------|------------------|------------------|
| 1 | правилата на заедницата |  |  |
| 2 | содржина |  |  |
| 3 | е-адреса |  |  |
| 4 | име |  |  |
| 5 | лозинка |  |  |
| 6 | потврда на лозинката |  |  |
| 7 | оценка |  |  |
| 8 | наслов |  |  |
| 9 | профилна слика |  |  |

## D2. Web interface strings

Shown directly in the page rather than as an error.

| # | Macedonian | Intended meaning | Where it appears |
|---|------------|------------------|------------------|
| 1 | Проверете го вашето сандаче | Check your inbox | Heading after signing up |
| 2 | Испративме порака со линк за потврда. | If the address can be used, we sent a message with a confirmation link. Open it to activate the account. | Body of that screen. **Deliberately vague** — it must not confirm whether the address was already registered |
| 3 | Испрати го линкот повторно | Send the link again | Button, when a confirmation link expired |
| 4 | Испративме нов линк. | If the address is awaiting confirmation, we sent a new link. | After pressing that button |
| 5 | Вашата е-адреса сè уште не е потврдена. | Your address is not confirmed yet. Check your inbox or request a new link. | Login, when the account exists but is unconfirmed |
| 6 | Е-адресата е потврдена | Address confirmed | Heading after clicking the confirmation link |
| 7 | Сметката е активна. Сега можете да се најавите. | The account is active. You can sign in now. | Body of that screen |
| 8 | Веќе е потврдена | Already confirmed | If the link is clicked twice |
| 9 | Оваа е-адреса е веќе потврдена. Најавете се за да продолжите. | This address is already confirmed. Sign in to continue. | Body of that screen |
| 10 | Линкот не е валиден | The link is not valid | Expired or tampered confirmation link |
| 11 | Линкот е неважечки или истечен. Побарајте нов подолу. | The confirmation link is invalid or expired. Request a new one below. | Body of that screen |
| 12 | Не сте најавени. | You are not signed in. | Acting without a session |
| 13 | Прикачувањето е неуспешно. | The upload failed. | Profile photo upload error |
| 14 | Невалиден профил за рецензија. | Invalid review target. | Malformed review submission |
| 15 | Темата е задолжителна. | The topic is required. | Malformed forum reply |
| 16 | Категоријата е задолжителна. | The category is required. | Malformed new topic |
| 17 | Серверот не врати сесија. Обидете се повторно. | The server did not return a session. Please try again. | Rare login/signup failure |
| 18 | Одјавени сте. | You are signed out. | After signing out |


---

## F. Emails

Full messages rather than fragments, so read them as a whole.

### Verification email — sent when someone signs up

> **Subject:** Потврдете ја вашата е-адреса — Zdravje360
>
> Здраво!
>
> Некој ја користеше оваа е-адреса за да отвори сметка на Zdravje360.
>
> **[ Потврди ја е-адресата ]**
>
> Линкот истекува за 60 минути.
>
> Ако не сте вие, слободно игнорирајте ја оваа порака — сметката нема да биде активирана.

*Note: worded so it works whether or not the reader expected it — the sign-up form
deliberately does not confirm whether an address is already registered.*

### "You already have an account" email — sent when someone signs up with an address that already exists

> **Subject:** Обид за регистрација со вашата е-адреса — Zdravje360
>
> Здраво, {име}
>
> Некој се обиде да отвори нова сметка на **Zdravje360** со вашата е-адреса. Веќе имате сметка, па не создадовме нова.
>
> Ако тоа сте биле вие, само најавете се:
>
> **[ Најави се ]**
>
> Ако сте ја заборавиле лозинката, поставете нова:
>
> **[ Промени ја лозинката ]**
>
> Ако не сте вие, не треба да преземате ништо — сметката е недопрена и никој не добил пристап.

### Welcome email — sent after the address is confirmed

> **Subject:** Добредојдовте на Zdravje360
>
> # Добредојдовте, {име}
>
> Вашата сметка на **Zdravje360** е подготвена. Можете да оставате рецензии и да учествувате на форумот (содржината се модерира пред објава).
>
> **[ Најави се ]**
>
> Содржината на платформата е само информативна и не ја заменува совет од лиценциран здравствен работник. При медицинска итност повикајте **194** или **112**.

### Password reset email

> **Subject:** Ресетирајте ја лозинката — Zdravje360
>
> Здраво!
>
> Добивме барање за ресетирање на лозинката за вашата сметка.
>
> **[ Постави нова лозинка ]**
>
> Линкот истекува за 60 минути.
>
> Ако не сте побарале ресетирање, игнорирајте ја оваа порака.

**One known gap:** these emails render inside Laravel's default wrapper, which is
still English — the "Regards", the trouble-clicking-the-button line, and the
copyright footer. That needs translating too; tell me if you want it done in the
same pass.

---

## G. Already corrected — please confirm

These two were wrong and are now fixed. Confirm the corrections are right.

| Was (Bulgarian) | Now (Macedonian) | Where |
|-----------------|------------------|-------|
| При медицинска итност повика**й**те 194 или 112 веднаш. | При медицинска итност повика**ј**те 194 или 112 веднаш. | Footer of every page, disclaimer, guidance, forum notice, facility banner, welcome email — 10 places |
| **Добре дојдовте** на Zdravje360 | **Добредојдовте** на Zdravje360 | Welcome email subject and heading |

---

## H. Symptom guidance flow and walkthrough fixes — new, please review

The seeded symptom-guidance questionnaire (`apps/api/database/seeders/TriageSeeder.php`)
was entirely **English**, including the emergency screen. It is now Macedonian.
This is a **translation only**: question codes, rules and clinical meaning are
unchanged (see `docs/triage-safety.md`). The triage tables have one text per
field and no language column, so there is no English copy kept beside it.

**Every row in H1 is safety-relevant**; the red flags and the emergency outcome
(rows 3–7, 25–26, 31) matter most.

### H1. Guidance flow (seeded content)

| # | Macedonian | English original | Where it appears |
|---|------------|------------------|------------------|
| 1 | Општи насоки за симптоми | General symptom guidance | Flow title |
| 2 | Одговорете на неколку општи прашања за да видите информативни следни чекори. Ова не е медицински совет и не може да поставува дијагнози. | Answer a few general questions to see informational next steps. This is not medical advice and cannot diagnose conditions. | Intro text before the questions |
| 3 | **Силна болка или притисок во градите** | Severe chest pain or pressure | Red flag |
| 4 | **Сериозно отежнато дишење** | Severe difficulty breathing | Red flag |
| 5 | **Обилно крварење што не престанува** | Heavy bleeding that does not stop | Red flag |
| 6 | **Ненадејна збунетост или неможност да се разбуди** | Sudden confusion or inability to wake | Red flag. Is "неможност да се разбуди" natural, or is "не може да се разбуди" better? |
| 7 | **Мисли за самоповредување или самоубиство** | Thoughts of self-harm or suicide | Red flag |
| 8 | Возрасна група | Age group | Question |
| 9 | Помлади од 18 години | Under 18 | Option |
| 10 | 18–64 години | 18–64 | Option |
| 11 | 65 години или постари | 65 or older | Option |
| 12 | Што најдобро го опишува она што ве загрижува? | What best describes your concern? | Question |
| 13 | Општи симптоми (болка, температура, замор) | General symptoms (pain, fever, fatigue) | Option |
| 14 | Повреда или незгода | Injury or accident | Option |
| 15 | Стрес или психичка благосостојба | Stress or wellbeing | Option |
| 16 | Колку се изразени симптомите денес? | How would you describe the severity today? | Question |
| 17 | Благи — се забележуваат, но се поднесливи | Mild — noticeable but manageable | Option |
| 18 | Умерени — ги попречуваат секојдневните активности | Moderate — interfering with daily activities | Option |
| 19 | Силни — многу тешко се поднесуваат | Severe — very difficult to manage | Option |
| 20 | Колку долго ги имате овие симптоми? | How long have you had these symptoms? | Question |
| 21 | Помалку од 24 часа | Less than 24 hours | Option |
| 22 | 1–7 дена | 1–7 days | Option |
| 23 | Повеќе од една недела | More than a week | Option |
| 24 | Броеви за итни случаи | Emergency numbers | Outcome link label |
| 25 | **Веднаш побарајте итна помош** | Seek emergency care now | Emergency outcome title |
| 26 | **Според вашите одговори, треба веднаш да ја повикате службата за итна помош. Не ја користете оваа веб-страница наместо итна медицинска помош.** | Based on your answers, you should contact emergency services immediately. Do not use this website instead of urgent care. | Emergency outcome text |
| 27 | Размислете за преглед наскоро | Consider care soon | Outcome title |
| 28 | Вашите одговори упатуваат дека можеби е разумно наскоро да разговарате со здравствен работник, особено ако симптомите се влошат. | Your answers suggest it may be reasonable to speak with a healthcare professional soon, especially if symptoms worsen. | Outcome text |
| 29 | Општи информации | General information | Outcome title (same as A24) |
| 30 | Според оваа листа за проверка, вашите одговори не упатуваат на непосредна итна состојба. Следете ги симптомите и побарајте стручен совет ако и понатаму сте загрижени. | Your answers do not suggest an immediate emergency on this checklist. Continue to monitor symptoms and seek professional advice if you remain concerned. | Outcome text |
| 31 | Назад на почетната страница / Прегледајте лекари / Прегледајте установи | Return home / Browse doctors / Browse facilities | Outcome link labels |

### H2. New interface strings (`apps/web/src/i18n/mk.ts`)

| # | Macedonian | Intended meaning | Where it appears |
|---|------------|------------------|------------------|
| 1 | **Повикај 194 (Брза помош)** | Call 194 (ambulance) | Tap-to-call button on the emergency outcome. Singular imperative, as on a button — or should it be "Повикајте"? |
| 2 | **112 — единствен број за итни случаи** | 112 — single emergency number | Second tap-to-call button |
| 3 | Вратете се на … за нова сесија. | Go back to [Symptom guidance] for a new session | Under a guidance result; was "за нов сесија" with the link text doubled |
| 4 | Прескокни до содржината | Skip to content | Keyboard skip link, first Tab on every page |
| 5 | Име, специјалност или поим | Name, specialty or term | Query field label on /search |
| 6 | Име или специјалност / Внесете име на лекар или специјалност | Name or specialty / Enter a doctor's name or a specialty | Doctors filter (the field now also matches specialties) |
| 7 | Име или одделение / Внесете име на установа или одделение | Name or department | Facilities filter (the field now also matches departments) |
| 8 | Најмалку 2 знаци за пребарување. | At least 2 characters to search | Hint under search fields (was "… по име.") |
| 9 | Исто така: {names} | Also: … | Doctor card, other specialties after the primary one |
| 10 | 1 тема, 1 установа, 1 лекар, 1 профил, 1 аптека, 1 производ, 1 одделение, 1 одговор, пред 1 ден, 1 запис во категоријата. | Singular forms | Counts ending in 1 (except 11) now use the singular; e.g. "1 теми" → "1 тема", "21 одговори" → "21 одговор". Confirm 21/101 take the singular in these phrases |
| 11 | **Веднаш повикајте итна помош** | Call emergency services now | **Safety-critical.** Heading shown the moment someone asks for emergency help, above the 194/112 buttons, before (or if) the server records the outcome |
| 12 | Јавното име мора да содржи барем две букви. | The display name must contain at least two letters | Register / account form (API, `lang/mk/validation.php`) |
| 13 | Не мешајте кирилица и латиница во ист збор од јавното име. | Do not mix Cyrillic and Latin in one word of the display name | Same |
| 14 | Јавното име не може да содржи титула или улога (на пр. „д-р“, „администратор“, „модератор“, „тим“) ниту името на платформата. | The display name cannot contain a title or role (e.g. "Dr", "administrator", "moderator", "team") or the platform's name | Same |
| 15 | Се согласувам со правилата на заедницата и разбирам дека форумот не дава дијагноза или третман. При итност ќе повикам 194 или 112 наместо да чекам одговор. | I agree to the community rules and understand that the forum does not give a diagnosis or treatment. In an emergency I will call 194 or 112 instead of waiting for a reply. | **Safety-relevant.** The single consent checkbox on the new-topic form (/forum/new), replacing three separate boxes; submit stays disabled until it is ticked |
| 16 | Потврдете ја согласноста пред испраќање. | Confirm your consent before sending | Error under that checkbox (was „Потврдете ги сите полиња пред испраќање.“) |

---

## Not in scope here

- **The existing UI copy** in `apps/web/src/i18n/mk.ts` — around 700 strings written
  before this work. I have not reviewed it. Given what turned up in the emergency
  line, it is worth reading in full at some point, but it is a separate job.
- **The admin panel**, which is English by your decision.
