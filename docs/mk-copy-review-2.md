# Macedonian copy — second pass (before the native review)

This pass covered `apps/web/src/i18n/mk.ts` (683 keys), `apps/api/lang/mk/**`, the
mail templates and notifications, the Laravel mail wrapper, the few Macedonian
strings in the admin, and hard-coded Macedonian in `.tsx`. The legal page bodies
(`apps/web/src/content/legal/*`) are being rewritten separately, so I did not touch them.

**I am not a native speaker.** I fixed things that are clearly wrong: grammar,
agreement, foreign letters and forms, calques, and inconsistent terms. I did not
"improve" wording that is just a matter of taste. Anything I wasn't sure about
is in [Questions for the native speaker](#questions-for-the-native-speaker)
and is still unchanged. The earlier sheet, `docs/mk-copy-review.md`,
is still where the native review happens. Its email rows and row D28 were updated to the new wording.

## Summary

- **mk.ts:** 49 values changed plus 1 new key (`reviews.filterStarsOne`).
  No keys were renamed, reordered or deleted. A scan of `src/` and `e2e/` found
  **two unused keys**: `forum.reply` („Одговор“) and `forum.topicSubmit`
  („Испрати тема“). They were reported, not removed. The `account.role*` keys look unused to a
  plain search but are read through `mk.account.*` in `lib/roles.ts`.
- **API / mail:** 2 notifications, 6 mail templates, 3 mailables and the
  UGC payload were fixed. The framework's mail wrapper was published and translated. `validation.php`
  had 2 messages that fell back to English, and those are fixed. 2 admin/digest labels were fixed too.
- **Forbidden letters** (й щ ъ ы ь э ю я ё): none left anywhere in `lang/`, `app/`,
  `resources/` or `mk.ts`.

### Top issues found

1. **Gender agreement in the moderation mails.** A forum reply was announced as
   „**Вашата одговор** … е **одобрена**“. The sentence was written for the feminine
   nouns *тема* and *рецензија*, and the masculine *одговор* was slotted into it.
   The templates now agree with the noun: „Вашиот одговор во темата „…“ е одобрен“.
2. **Welcome mail:** „не **ја** заменува **совет**“ had a feminine clitic on a masculine,
   indefinite noun. It now reads „не го заменува советот“, as in the site footer.
3. **„Обиди повторно“** on the two retry buttons was missing the reflexive *се*.
4. **„што само“** was the forum's "just now" label. That is not Macedonian
   (it reads as "which only") → „пред малку“.
5. **„По оцена“** used the Serbian form → „По оценка“. The same Serbian form was in the
   design gallery's „Оцена“.
6. **„1 ѕвезди“** appeared in the review-rating filter (plural after 1).
7. **The English mail wrapper** said "Regards", "If you're having trouble clicking…"
   and "All rights reserved". It is now Macedonian, hard-coded in the published
   views, because queued mail never sees the request locale.
8. **Untranslated validation:** `accepted_terms` (symptom-guidance consent) rendered as
   „Полето **accepted terms** мора да биде прифатено.“ The `present` rule had no message.
9. **Calques:** „Форум модератор“, „форум активност“, „форум пораки“, „третирајте ги
   како сигнал“, „унифициран интерфејс“, „генерички глобален“, „Правно“ as a heading,
   „Одрекување“ for *disclaimer*.
10. **Terminology drift:** „Е-пошта“ was used for the address field while the API and the mails said „е-адреса“.
    „доктор“ was mixed with „лекар“. „ресетирање“ was mixed with „промена“ of the password.

## Changes — web (`apps/web/src/i18n/mk.ts`)

| # | Key | Before | After | Reason |
|---|-----|--------|-------|--------|
| W1 | `nav.searchLandmark` | Пребарување низ сајтот | Пребарување низ платформата | Terminology: „сајт“ is a colloquial anglicism; the rest of the UI says „платформата“. |
| W2 | `footer.legal` | Правно | Правни информации | Calque of English „Legal“ (adverb used as a heading). Also the eyebrow on legal pages. |
| W3 | `home.howStep3Body` | Рецензиите се модерирани; третирајте ги како дополнителен сигнал, не како дијагноза. | Рецензиите се модерирани; сфатете ги како дополнителна информација, а не како дијагноза. | Calques „третирајте ги како“ (treat them as) and „сигнал“; „а не“ for the contrast. |
| W4 | `home.transparencyItem1Body` | Корисниците добиваат поорганизиран и појасен начин да дојдат до следен чекор. | Корисниците добиваат поорганизиран и појасен начин да дојдат до следниот чекор. | Definite form: a specific next step. |
| W5 | `home.transparencyItem2Body` | Лекари, клиники и аптеки се споредуваат преку унифициран интерфејс. | Лекарите, клиниките и аптеките ги споредувате на едно место и на ист начин. | Jargon („унифициран интерфејс“) that older users will not follow; plain, active wording. |
| W6 | `home.transparencyItem3Body` | Платформата е создадена за Северна Македонија, не е генерички глобален директориум. | Платформата е создадена за Северна Македонија, а не како општ светски директориум. | Anglicisms „генерички глобален“; comma splice. |
| W7 | `home.trustRowModerated` | Рецензиите и објавите се проверуваат пред објава | Рецензиите и објавите се проверуваат пред да станат јавни | Repetition „објавите … пред објава“. |
| W8 | `home.guideBody` | Одговорете на неколку кратки прашања и ќе ви покажеме дали е доволна грижа дома, аптека, матичен лекар или итна помош. | Одговорете на неколку кратки прашања и ќе ви покажеме дали е доволна грижа дома или ви треба аптека, матичен лекар или итна помош. | „дали е доволна … аптека, матичен лекар“ did not parse: only home care can be „доволна“. |
| W9 | `errors.retry` | Обиди повторно | Обиди се повторно | Grammar: „обидува се“ is reflexive; „Обиди повторно“ is missing „се“. |
| W10 | `search.retry` | Обиди повторно | Обиди се повторно | Same as errors.retry. |
| W11 | `ui.verified` | Верификуван | Проверен | Native word; matches „Проверени профили“ in about.valueTrust. |
| W11b | `ui.verified` → `ui.staffTag` (2026-10-07) | Проверен | Тим | The tag is the forum's staff marker (the role is passed in); „Проверен“ / „Верификуван“ belong to profile badges only. „Проверени профили“ in about.valueTrust became „Верификувани профили каде што е можно“ (not every profile is checked). |
| W12 | `ui.emailRequired` | Внесете ја вашата е-пошта. | Внесете ја вашата е-адреса. | Terminology: the address is „е-адреса“ (as in the API messages and emails); „е-пошта“ is the medium. |
| W13 | `ui.emailInvalid` | Внесете валидна е-пошта, на пример ime@primer.mk. | Внесете валидна е-адреса, на пример ime@primer.mk. | Terminology, as above. |
| W14 | `common.email` | Е-пошта | Е-адреса | Terminology, as above (field and contact-row label). |
| W15 | `auth.loginDescription` | Најавете се за рецензии и форум. Немате сметка? Регистрирајте се. | Најавете се за да пишувате рецензии и да учествувате во форумот. Немате сметка? Регистрирајте се. | „Најавете се за рецензии и форум“ is telegraphic; full clause is clearer. |
| W16 | `auth.registerDescription` | Создадете членска сметка за рецензии и форум. | Отворете сметка за да пишувате рецензии и да учествувате во форумот. | Same telegraphic phrasing; „отворете сметка“ matches the emails. |
| W17 | `auth.registerNameHelp` | Останува приватно: го гледате само вие во сметката и администраторите. | Останува приватно: видливо е само за вас и за администраторите. | Broken agreement: 2pl verb „гледате“ with the compound subject „вие … и администраторите“. |
| W18 | `auth.registerDisplayNameHelp` | Се прикажува јавно покрај вашите рецензии и пораки во форумот. Предлогот е име и почетна буква од презимето; може да го промените сега или подоцна во сметката. | Се прикажува јавно покрај вашите рецензии и пораки во форумот. Предлогот е име и почетна буква од презимето; можете да го промените сега или подоцна во сметката. | Agreement with formal „вие“: „можете“, not impersonal „може“. |
| W19 | `auth.registerPasswordConfirm` | Потврди лозинка | Потврда на лозинката | Field label as a noun phrase (other labels are nouns: „Лозинка“, „Јавно име“); matches the API attribute name. |
| W20 | `auth.forgotPasswordDescription` | Внесете ја е-поштата за сметката. Ќе ви испратиме линк за ресетирање на лозинката. | Внесете ја е-адресата на вашата сметка. Ќе ви испратиме линк за промена на лозинката. | Terminology: „е-адреса“; „промена на лозинката“ instead of the anglicism „ресетирање“ (the API already says „промена“). |
| W21 | `auth.email` | Е-пошта | Е-адреса | Terminology: login/register/reset field label. |
| W22 | `search.description` | Пребарајте истовремено во лекари, установи, аптеки и производи. За еден тип директориум исклучиво, користете напредно пребарување. | Пребарувајте истовремено низ лекари, установи, аптеки и производи. За пребарување само во еден директориум, користете напредно пребарување. | Word order „За еден тип директориум исклучиво“ is an English calque; aligned with search.hubIntro. |
| W23 | `search.unifiedHint` | Приказ до 5 ставки по категорија (лекари, установи, форум и останато). Користете ги линковите за цели филтрирани листи. | Се прикажуваат до 5 резултати по категорија (лекари, установи, форум и останато). За целосна листа со филтри, користете ги линковите. | Nominal style („Приказ до…“) and „цели филтрирани листи“ read as translation. |
| W24 | `search.emptyBody` | Проверете го правописот, пробајте пократок поим или отстранете го градот. Можете да пребарувате и во еден директориум. | Проверете го правописот, обидете се со пократок поим или отстранете го градот. Можете да пребарувате и во еден директориум. | „пробајте“ is colloquial; „обидете се“ matches the rest of the UI. |
| W25 | `filters.sortByRating` | По оцена (рецензии) | По оценка (рецензии) | Spelling: „оцена“ is the Serbian form; Macedonian is „оценка“ (used everywhere else). |
| W26 | `account.email` | Е-пошта | Е-адреса | Terminology, as auth.email. |
| W27 | `account.roleForumModerator` | Форум модератор | Модератор на форумот | Noun-noun compound „Форум модератор“ is an English calque. |
| W28 | `account.forumActivity` | Моја форум активност | Моја активност на форумот | Same calque („форум активност“). |
| W29 | `account.subNavAria` | Потнавигација на сметката | Навигација низ сметката | „Потнавигација“ is not an established word; screen-reader label should be plain. |
| W30 | `account.profilePhotoUnlocked` | Прикачете JPG, PNG или WebP (се зачувува како WebP). | Прикачете слика во формат JPG, PNG или WebP. | Says what to upload; the storage format is a technical detail users cannot act on. |
| W31 | `account.profilePhotoLocked` | Профилната слика е заклучена. Потребни се најмалку {required} одобрени форум пораки (имате {count}). | Профилната слика е заклучена. Потребни се најмалку {required} одобрени пораки на форумот (имате {count}). | Calque „форум пораки“. |
| W32 | `account.hubHeroDescription` | Управувајте со профилот, рецензиите и форум активноста. | Управувајте со профилот, рецензиите и активноста на форумот. | Calque „форум активноста“. |
| W33 | `account.profilePhotoProgress` | {count} од {required} одобрени форум пораки | {count} од {required} одобрени пораки на форумот | Calque „форум пораки“. |
| W34 | `doctors.cityPlaceholder` | Пр. Скопје, Битола, Охрид | На пр. Скопје, Битола, Охрид | Standard abbreviation is „на пр.“ (as in validation.php). |
| W35 | `doctors.about` | За докторот | За лекарот | Terminology: one term, „лекар“. |
| W36 | `facilities.cityPlaceholder` | Пр. Скопје, Битола, Охрид | На пр. Скопје, Битола, Охрид | As doctors.cityPlaceholder. |
| W37 | `pharmacies.cityPlaceholder` | Пр. Скопје, Битола, Охрид | На пр. Скопје, Битола, Охрид | As doctors.cityPlaceholder. |
| W38 | `reviews.body` | Коментар (опционално, мин. 10 знаци ако е пополнет) | Коментар (по желба; ако пишувате, најмалку 10 знаци) | Abbreviation „мин.“ and passive „ако е пополнет“ are hard to read; plain wording. |
| W39 | `about.valueAccess` | Достапност низ цела држава | Достапност низ целата држава | Definite form required: „низ целата држава“. |
| W40 | `about.teamBody` | Го градиме Zdravje360 за да здравствените информации во Македонија бидат појасни, поорганизирани и полесно достапни. | Го градиме Zdravje360 за здравствените информации во Македонија да бидат појасни, поорганизирани и полесно достапни. | Word order: „за да“ must sit next to the verb („за … да бидат“). |
| W41 | `disclaimerPage.badge` | Одрекување | Ограничување на одговорност | „Одрекување“ (renunciation) is a literal rendering of „disclaimer“; the footer link already says „Ограничување на одговорност“. |
| W42 | `disclaimerPage.notDoctorTitle` | Не е замена за доктор | Не е замена за лекар | Terminology: „лекар“. |
| W43 | `disclaimerPage.emergencyBody` | При болка во градите, отежнато дишење, нагло влошување или сомневање за итност — повикајте 194 веднаш. | При болка во градите, отежнато дишење, нагло влошување или сомневање за итност — повикајте 194 или 112 веднаш. | Safety consistency: every other emergency line gives both 194 and 112. |
| W44 | `forum.authorForumModerator` | Форум модератор | Модератор на форумот | As account.roleForumModerator. |
| W45 | `forum.activityJustNow` | што само | пред малку | „што само“ is not Macedonian for „just now“ (it reads as „which only“); „пред малку“ fits the other „пред …“ labels. |
| W46 | `forum.originalPost` | Поставување на темата | Првична објава | Screen-reader prefix before the author's name; „Поставување на темата“ (the act of posting) did not name the post. |
| W47 | `guidance.description` | Општи информации за размислување за следни чекори. Не е медицински совет. | Општи информации што ќе ви помогнат да одлучите за следниот чекор. Не е медицински совет. | „за размислување за следни чекори“ is a word-for-word calque with two „за“. |
| W48 | `guidance.acceptLabel` | Разбирам дека ова се само општи информации, не медицински совет, и не е за одложување итна нега. | Разбирам дека ова се само општи информации, а не медицински совет, и дека поради нив не треба да одложувам итна помош. | Safety-relevant consent: the last clause had no subject („и не е за одложување…“). „итна помош“ is the term used on the rest of the screen. |
| W49 | `guidance.starting` | Се стартува… | Се започнува… | „стартува“ is a loanword; the screen's button says „Започни“. |
| W50 | `reviews.filterStarsOne (new)` | „1 ѕвезди“ (from filterStars) | {stars} ѕвезда | The rating filter said „1 ѕвезди“. One new key added right after filterStars; reviews-panel.tsx now uses tCount. Test: reviews-panel.test.tsx. |

Also in the web app:

- `src/app/design-system/page.tsx` (the internal gallery, not indexed):
  „Оцена“ → „Оценка“, „Име за приказ“ → „Јавно име“, „Е-пошта“ → „Е-адреса“.
- `src/lib/api/public-settings.ts`: the fallback footer disclaimer was changed in the same way as A11 below.

## Changes — API, mail, admin

| # | Where | Before | After | Reason |
|---|-------|--------|-------|--------|
| A1 | Mail wrapper (`resources/views/vendor/notifications/email.blade.php`, new) | Regards, | Поздрав, | Wrapper was English. Hard-coded: queue workers do not get the request locale. |
| A2 | Same | If you're having trouble clicking the "…" button, copy and paste the URL below into your web browser: | Ако копчето „…“ не работи, копирајте ја адресата подолу и залепете ја во вашиот прелистувач: | Same. |
| A3 | Same (fallback greetings, unused today because every notification sets one) | Hello! / Whoops! | Здраво! / Настана проблем | Same. |
| A4 | `vendor/mail/html/message.blade.php`, `vendor/mail/text/message.blade.php` (new) | © 2026 Zdravje360. All rights reserved. | © 2026 Zdravje360. Сите права задржани. | Same wording as the site footer. |
| A5 | `VerifyEmailNotification` | Некој ја користеше оваа е-адреса… | Некој ја искористи оваа е-адреса… | One completed act → perfective aorist. The imperfect „користеше“ suggests repeated or ongoing use. |
| A6 | Verify + reset notifications | Линкот истекува за 60 минути. | Линкот важи 60 минути. | Plainer. „истекува за 60 минути“ can read as "expires in 60 minutes from now", which is right, but „важи“ is simpler for older readers. *(taste — native may revert)* |
| A7 | `VerifyEmailNotification`, `account-exists` | Ако не сте вие, … | Ако тоа не сте биле вие, … | Parallel to „Ако тоа сте биле вие“ in the same mail. Past tense: it refers to the sign-up that already happened. |
| A8 | `ResetPasswordNotification` subject | Ресетирајте ја лозинката — Zdravje360 | Промена на лозинката — Zdravje360 | Terminology: „промена“ (as in `passwords.php` and the web). |
| A9 | Same, body | Добивме барање за ресетирање на лозинката за вашата сметка. / Ако не сте побарале ресетирање, игнорирајте ја оваа порака. | Добивме барање за промена на лозинката на вашата сметка. / Ако не сте побарале промена на лозинката, игнорирајте ја оваа порака — вашата лозинка останува иста. | Same, and the reassurance at the end says what happens if you do nothing. |
| A10 | `welcome.blade.php` | …не ја заменува совет од лиценциран здравствен работник. / учествувате на форумот | …не го заменува советот од лиценциран здравствен работник. / учествувате во форумот | Clitic/article agreement. „во форумот“ as on the web. |
| A11 | `SiteSetting::DEFAULT_FOOTER_DISCLAIMER` | Насоки за симптоми се само информативни. | Насоките за симптоми се само информативни. | Definite subject. Only the default changed; a value already saved in admin is untouched. |
| A12 | `ugc-approved/-rejected/-submitted` + `UgcMailer` | Вашата {одговор на форумот} „…“ е одобрена / примена / не беше објавена | Вашиот одговор во темата „…“ е одобрен / примен / не беше објавен. Topic: „Вашата тема „…“ …“. Review: „Вашата рецензија за „д-р …“ …“ | **Gender agreement** (top issue 1). The title in quotes is the topic for a reply and the doctor/facility for a review, and the label now says so. |
| A13 | `ugc-approved` | …е одобрена и сега е видлива на Zdravje360. | …е одобрена и сега може да се види на Zdravje360. | Avoids a second gendered adjective. |
| A14 | `ugc-rejected` | Можете да ја проверите состојбата на вашата сметка. | Статусот на сите ваши објави можете да го видите во вашата сметка. | The original sentence meant "the state of your account" — it was not about the posts. |
| A15 | `UgcSubmittedMail` subject | Примено — чека модерација — Zdravje360 | Вашата објава чека модерација — Zdravje360 | Two dashes; „Примено“ had no subject. |
| A16 | UGC + digest mails | Здраво {име}, | Здраво, {име}, | A vocative is set off by commas (account-exists already had it). |
| A17 | `moderation-digest.blade.php` | Имате **1** ставки што чекаат модерација. / Отвори админ панел | Имате **1** ставка што чека модерација. / Отвори го административниот панел | Singular after 1 (same rule as the web's `tCount`). „админ панел“ is slang. |
| A18 | `ModerationDigestService` labels | Теми на форум / Одговори на форум | Теми на форумот / Одговори на форумот | Definite form. |
| A19 | `lang/mk/validation.php` `password.uncompromised` | Оваа :attribute се појавила во протекување на податоци. Изберете друга лозинка. | Оваа лозинка се појавила меѓу податоци протечени од други сервиси. Изберете друга лозинка. | The agreement depended on the attribute's gender. „протекување на податоци“ is jargon. |
| A20 | Same, new `present` | *(English fallback)* | Полето :attribute мора да биде испратено. | Guidance answers fell back to English. |
| A21 | Same, new `custom.accepted_community_rules` / `custom.accepted_terms` | Полето правилата на заедницата мора да биде прифатено. / Полето accepted terms мора да биде прифатено. | Потврдете дека ги прифаќате правилата на заедницата. / Потврдете дека разбирате дека насоките се само општи информации. | The first did not read as a sentence. The second was half English. English equivalents were added to `lang/en`. |
| A22 | Same, new `attributes` | — | accepted_terms → условите, answers.*.values → одговор, q → пребарување, city → град | Attributes that could otherwise surface raw. |
| A23 | `DoctorForm` helper (admin, English) | “Истакнати доктори” | “Истакнати лекари” | The quoted section title did not match the site, which says „Истакнати лекари“. |

Regression tests: `tests/Feature/MailCopyTest.php` (wrapper under the `en` locale,
welcome grammar, reply gender via the real approval flow, review/reply receipts,
digest singular) and
`TranslationCompletenessTest::test_consent_and_guidance_answer_messages_are_fully_translated`.
I checked that each one fails against the code before the fix.

`lang/mk/api.php` is marked "natively reviewed" and I left it alone.
`auth.php` and `passwords.php` read correctly to me and are unchanged.

## Glossary — one term per concept

| Concept | Use | Avoid | Notes |
|---------|-----|-------|-------|
| e-mail address (the thing you type) | **е-адреса** | е-пошта, мејл, е-маил | Field labels too: „Е-адреса“. |
| e-mail (the medium / inbox) | **е-пошта**, **сандаче** | | „проверете го сандачето“, „папка за непосакувана пошта (spam)“ |
| account | **сметка** | профил, акаунт | „Отворете сметка“, „Членска сметка“ |
| profile of a doctor / facility / pharmacy | **профил** | | The account tab is also „Профил“ (`nav.tabProfile`) — see questions. |
| doctor | **лекар** | доктор, врач | „д-р“ stays as the title. |
| facility | **установа** | институција | Specific kinds: клиника, болница, лабораторија, аптека. |
| pharmacist | **фармацевт** | аптекар | |
| review (text + stars) | **рецензија** | оценка, коментар, осврт | |
| rating (the stars) | **оценка** | оцена (Serbian) | |
| registered user | **член** | корисник | „корисник/корисници“ only for people using the site in general. |
| sign in / out | **најава / најави се**, **одјава / одјави се** | логирање, пријава | „пријава“ is kept free for *reports* (reporting content). |
| sign up | **регистрација / регистрирај се** | | |
| password reset | **промена на лозинката**, **нова лозинка** | ресетирање | |
| forum topic / reply / post | **тема / одговор / објава** | пост, коментар | „објава“ = any published item. |
| moderation | **модерација**, „се проверува пред објава“ | | |
| the site | **платформата**; a single page is **страница** | сајт, портал | External site of a doctor: „Веб-страница“. |
| disclaimer | **ограничување на одговорност** | одрекување | |
| symptom guidance | **насоки за симптоми** | тријажа (internal only) | |
| directions (map) | **насоки** | | Same word as guidance — see questions. |
| emergency | **итна помош**, **итен случај**, **итност** | | Always give **194 или 112**. |
| link | **линк** | врска | Kept as is; see questions. |
| search | **пребарај / пребарување** | | Tab bar uses „Барај“ (short). |
| verified / unverified (profile badge) | **верификуван / неверификуван** (doctors); **верификувана / неверификувана** (facilities and pharmacies, without a noun); plural **верификувани**; verb **верификува**; noun **верификација** | верифициран, проверен | Owner's decision 2026-10-07: the -ува form everywhere (UI, admin labels, docs). Both forms occur in Macedonian; please confirm with a native speaker. Staff accounts in the forum carry „Тим“ or their role, never „Верификуван“. |

**Address: lowercase „вие / ваш / ви“** in running text, capitalised only at the start of a
sentence. This is how the whole UI was already written, and it is common in Macedonian
interfaces. The capitalised form („Вие“, „Ваш“) belongs to personal letters and
official correspondence. I found no mid-sentence capitals and no lapses into the singular
in instructional text.

**Buttons: 2nd person singular imperative** („Пребарај“, „Најави се“, „Испрати
рецензија“, „Обиди се повторно“). **Instructions, hints and errors: plural „вие“**
(„Внесете…“, „Проверете…“). This was already the pattern and I kept it. Exceptions
I left alone: `reviews.write` „Напишете рецензија“ and the call-to-action headings.
See the questions.

**Punctuation:** quotation marks „…“. A spaced long dash „ — “ for
asides. An en dash without spaces for ranges (18–64, 08:00–14:00). No space before
„?“ / „!“. The ellipsis character „…“ for "in progress" states.

## Questions for the native speaker

1. **Counted plural (бројна множина).** After numbers, the UI uses the ordinary plural
   for masculine nouns: „5 профили“, „5 резултати“, „5 производи“, „5 записи“, „10 знаци“,
   „{count} одговори“. One string uses the counted form („барем два **знака**“), while
   „пред {count} **дена** / **часа**“ do as well. As I understand it, the counted form
   (профила, резултата, знака…) is the traditional norm after numerals, while the plain
   plural is widespread and acceptable. Which do you want? I left all of them as they were.
2. **21, 31, 101 + singular.** The UI says „21 одговор“, „31 тема“ (singular after numbers
   ending in 1, except 11 — the CLDR rule). Is that right with digits, or should it be
   „21 одговори“?
3. **„Здравје360“ vs „Zdravje360“.** The logo/wordmark is Cyrillic, while titles, mails and copy are Latin.
   Intentional brand choice?
4. **„Македонија“ vs „Северна Македонија“.** Both appear („Создадено за корисници …
   во Македонија“, „…во Северна Македонија“). Should one be used everywhere?
5. **„Насоки“ means two things:** symptom guidance and map directions (`directory.directions`,
   `facilities.getDirections`). On a doctor's page both can be visible. Should the map one become
   „Упатства до тука“ or „Како да стигнете“?
6. **„Одговори“** is both the replies heading and the reply button (`forum.replyToTopic`).
   Should the button be „Одговори на темата“?
7. **Tab bar „Барај“ vs „Пребарај“ elsewhere.** The short form saves space. Is it fine?
8. **„Регистрацијата е привремено исклучена“ (web) vs „…оневозможена“ (API).** Should they be unified?
9. **„Линкот е неважечки или истечен“** — does „истечен“ sound right for a link, or
   „линкот истекол“?
10. **„линк“ vs „врска“.** I kept „линк“ as more familiar to older users. Do you agree?
11. **„Исто така: {names}“** (other specialties on a doctor card) — natural, or
    „И: …“ / „Други специјалности: …“?
12. **„Нешто тргна наопаку“** (error page title) — natural, or „Настана грешка“?
13. **„Повикај 194 (Брза помош)“** — singular, as on every other button. Earlier sheet H2-1 asks the same.
14. **Currency:** prices show „MKD“ (`products.priceFrom`), while the admin example says „МКД“.
    Should it be „ден.“?
15. **„{years} год. искуство“** — is the abbreviation fine, or should it be written out („години искуство“)?
16. **Sheet H (symptom guidance seed, `TriageSeeder.php`)** — I did not edit it; it is outside
    this pass's files. Two notes for you:
    - H1-15 „Стрес или **психичка благосостојба**“: *благосостојба* usually means material
      welfare/prosperity. Is „психичко здравје“ or „душевна благосостојба“ better?
    - H1-31 „Прегледајте лекари / установи“ vs the web's „Пребарај лекари“ / „Најди
      лекар“. Should they match?
    - H1-6 „неможност да се разбуди“: the sheet already asks this.
17. **Changes that are more taste than rule** — feel free to revert: A6 („важи“), W5
    (transparencyItem2Body rewrite), W30 (dropped "saved as WebP"), W38 (review comment hint),
    W49 (`guidance.starting`).

## Hard-coded Macedonian outside the dictionaries

None in production `.tsx` components: everything user-facing in `src/components` and
`src/app` goes through `t()`. What remains:

| File | What | Status |
|------|------|--------|
| `apps/web/src/app/design-system/page.tsx`, `gallery-demos.tsx` | Sample copy for the internal component gallery (noindex) | Intentional sample content; I fixed 3 strings to match the glossary. |
| `apps/web/src/lib/api/public-settings.ts` | Fallback footer emergency/disclaimer text when settings cannot load | Duplicates `SiteSetting` defaults by design; I kept them in sync. |
| `apps/web/src/lib/form-validation.ts` | Regexes that recognise API validation messages (`е задолжително`, `најмалку N знаци`…) | Not copy, but **coupled to `lang/mk/validation.php`**: rewording those messages can break field mapping. I left those messages unchanged. |
| `apps/web/src/lib/office-hours.ts`, `user-initials.ts` | Day names and titles used for parsing | Not displayed. |
| `apps/api/app/Mail/*.php`, `app/Notifications/*.php`, `resources/views/mail/*` | Mail subjects and bodies | Hard-coded Macedonian (not in `lang/`). Fine while mail is Macedonian-only. |
| `apps/api/app/Support/UgcMailer.php`, `app/Services/ModerationDigestService.php` | Content labels and button labels in mail („Мој форум“, „Види ја темата“, „Рецензии“…) | As above. |
| `apps/api/app/Models/SiteSetting.php` | Default footer texts | Editable in admin. |
