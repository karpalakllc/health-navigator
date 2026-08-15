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

## Not in scope here

- **The existing UI copy** in `apps/web/src/i18n/mk.ts` — around 700 strings written
  before this work. I have not reviewed it. Given what turned up in the emergency
  line, it is worth reading in full at some point, but it is a separate job.
- **The admin panel**, which is English by your decision.
