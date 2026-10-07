# „Каде веднаш“, healthcare guides and „Дали ви помогна?“

Three things that help a visitor act, built next to symptom guidance (T-FIND,
2026-10-07):

1. **„Каде веднаш“** — urgent care by city: emergency departments, emergency
   medical services, on-duty clinics, dental emergency.
2. **Водичи низ здравството** — six static guides on how public healthcare
   works, from official sources.
3. **„Дали ви помогна?“** — an anonymous yes/no widget and step counters, with
   an admin report.

This file is also the **link contract** for symptom guidance (T-ENGINE) and
the integrator: § 1 (deep links), § 4 (feedback and drop-off).

## Routes

| Route | What | Indexed |
|---|---|---|
| `/urgent-care` | Whole country | yes (no query) |
| `/urgent-care/[city]` | „Итна помош во Битола“ — `city` is the Latin id from `lib/mk-places.ts` (`bitola`, `skopje`, `karpos`, `strumica`, …) | yes, while the city has at least one place and no `?type` |
| `/urgent-care?city=…&type=…` | Deep-link form; a city on the territorial list **redirects** to its city page (keeping `type`), any other stays as a filtered, unindexed view | no |
| `/guides` | Guide index | yes |
| `/guides/[slug]` | One guide (`kako-do-uput`, `shto-pokriva-fzom`, `moj-termin`, `maticen-lekar`, `prava-na-pacientite`, `participacija`) | yes |

Why `/urgent-care` and `/guides` rather than `/kade-vednas` / `/vodic`: every
public route is an English path with a Macedonian page (`/doctors`,
`/facilities`, `/guidance`, `/transparency`, `/privacy`); a transliterated
path would be the only one of its kind and has no agreed spelling
(`kade-vednas`/`kade-vednash`). The Macedonian words are in the titles,
breadcrumbs, footer and city pages („Итна помош во Битола“), which is what
search engines and visitors read. Both are in the footer and the sitemap
(city pages only for cities with places).

## 1. Link contract (for guidance outcomes and other pages)

Use `urgentCareHref()` from `apps/web/src/lib/urgent-care.ts`; do not build
the URL by hand.

```ts
import { urgentCareHref } from "@/lib/urgent-care";

urgentCareHref({});                              // /urgent-care
urgentCareHref({ type: "ed" });                  // /urgent-care?type=ed
urgentCareHref({ city: "Битола" });              // /urgent-care/bitola
urgentCareHref({ city: "Bitola", type: "ems" }); // /urgent-care/bitola?type=ems
urgentCareHref({ city: "Карпош" });              // /urgent-care/karpos
urgentCareHref({ city: "Друго место" });         // /urgent-care?city=…
```

`type` (`UrgentService`):

| value | Meaning | Facility column |
|---|---|---|
| `ed` | Emergency department — ургентен центар / ургентно одделение | `has_emergency_services` |
| `ems` | Служба за итна медицинска помош (health centres; the 194 teams) | `has_emergency_medical_service` |
| `clinic` | Дежурна служба / амбуланта — on duty outside regular hours | `has_on_duty_clinic` |
| `dental` | Итна / дежурна стоматолошка служба | `has_dental_emergency` |

Suggested mapping for guidance outcome levels (the engine decides):
`emergency_now` → call 194/112 first (the outcome's own tel links), then
`urgentCareHref({ city, type: "ed" })`; `urgent_same_day` →
`urgentCareHref({ city })` (all services); a dental outcome → `type: "dental"`.
The city is whatever the session already has (a name in either script); the
visitor's position is never needed.

The page itself carries the 194/112 strip (owner rule: allowed on this page
only, outside guidance).

## 2. Data

### Columns (`facilities`, migration `2026_10_18_200000`)

`has_emergency_services` (existing — now explicitly the **emergency
department**), `has_emergency_medical_service`, `has_on_duty_clinic`,
`has_dental_emergency`, `is_open_24h`, `emergency_hours` (same `{day: hours}`
format as `office_hours`), `emergency_phone`, `urgent_care_note` (public, one
line), `urgent_care_checked_at` (staff confirmation), `urgent_care_evidence`
(internal JSON: what the imports read, which flags were set automatically).

**Emergency department status** (`emergency_department_status`, migration
`2026_10_18_200002`, owner decision 2026-10-07): `confirmed` (mirrors
`has_emergency_services = true`; the model keeps the two in step),
`unconfirmed_likely` — a **public general or clinical hospital** („ЈЗУ Општа
/ Клиничка / Градска општа болница“, ownership public or a „ЈЗУ“ name) with no
emergency unit named in the sources; shown publicly as „Итно одделение
(непотврдено)“ with the main phone and a calm note, after the confirmed ones
— `none` (staff checked: no emergency department), or null (unknown).
Special hospitals and private hospitals are never „likely“: they count only
with evidence. Strong evidence turns `null`/`unconfirmed_likely` into
`confirmed`; `none`, or any staff check (`urgent_care_checked_at`), is never
changed by the deriver.

**Hours are never guessed.** With `emergency_hours` empty and `is_open_24h`
off the page says „Работното време не е потврдено — ако можете, јавете се
пред да тргнете.“ and never shows „Отворено“. `is_open_24h` is set
automatically only when the institution's own website ties 24/7 to the
urgent service in one clause („Ургентен центар 24/7“, „ургентна служба 00–24
ч.“, „Итна медицинска помош: 24 часа“).

### Where the flags come from

`App\Support\UrgentCare\UrgentCareClassifier` reads, per facility: ФЗОМ work
units (`doctor_facility.work_unit`), website departments and hours text
(`source_records` of the website import), and published department names.

- **strong** wording switches a flag on: „Ургентен (хируршки) центар“,
  „Ургентна амбуланта“, a unit named exactly „Ургентни состојби“ / „Ургентна
  медицина“ → `ed`; „(Служба за) итна (медицинска) помош“ → `ems`; „итна /
  дежурна стоматолошка …“, or „Дежурна служба“ at a public dental centre →
  `dental`.
- **candidate** wording is only listed for staff: wards („Оддел за ургентна
  психијатрија / инфектологија“, „Итна гинекологија“, „итна трауматологија“),
  „Дежурна служба“ without saying of what, a private dental practice's „Итни
  случаи“, a general/clinical hospital without a named emergency unit, and
  24/7 said of the whole place („Секој ден 24/7“, „Болницата работи 24 часа“).

`UrgentCareDeriver` applies it at the end of every ФЗОМ and website import
(dry runs roll back with the run), and by hand:

```sh
php artisan urgent-care:derive --dry-run
php artisan urgent-care:derive
```

Rules: only strong evidence; never switches a flag off; once staff set
„Confirmed by staff“ (`urgent_care_checked_at`) nothing is changed again; a
flag the deriver set that is off now was switched off by staff and stays off.
Writes bypass model events (like the imports: no activity-log line per
facility). Import runs count `urgent_care_flags_set` and list each change in
the diff CSV.

### Admin

Facility form → **Urgent care („Каде веднаш“)**: the emergency department
status (Confirmed / Likely, not confirmed / No / Unknown), the other three services, 24/7,
hours, direct line, public note, „Confirmed by staff“, and the import
evidence (read only). Facilities list → filter **Urgent care**: per service,
any, **„Emergency department likely, not confirmed“**, or **„Import
evidence, not confirmed by staff“** — the staff to-do lists. The list shows
the status as a badge.

### Coverage on a copy of the real data (2026-10-07)

`urgent-care:derive` on a copy of `zdravje_real`: **39 flags on 31
facilities** (23 published, 8 unpublished drafts); 63 facilities with any
evidence, 37 with candidates to check.

- `ems` (22 health centres, all from ФЗОМ work units and/or website
  departments): Битола, Валандово, Вевчани, Велес, Виница, Делчево, Демир
  Хисар, Кичево, Кратово, Крива Паланка, Крушево, Куманово, Македонски Брод,
  Неготино, Охрид, Пробиштип, Радовиш, Ресен, Скопје (ЗД Скопје), Струга,
  Тетово, Штип.
- `ed` (8): Скопје — ТОАРИЛУЦ (Ургентен центар), Св. Наум Охридски (Ургентен
  центар), ГОБ 8 Септември (website „Ургентен центар“), Аџибадем Систина
  (Адултен ургентен центар), Детска клиника (website hours „24/7 (ургентна
  служба)“ — **pediatric**: staff should add the note „за деца“), a cardiology
  clinic (website „ургентна служба 00–24 ч.“); Битола — Клиничка болница
  (Ургентна медицина); Куманово — Општа болница (Ургентни состојби).
- `dental` (3): ЗД Битола, ЗД Тетово (website), Стоматолошки клинички центар
  Скопје (Дежурна служба).
- `is_open_24h` (6), each from the institution's own hours text.

After the „likely“ rule: **16 public general/clinical hospitals are
`unconfirmed_likely`** (14 published): Битола (КБ), Велес, Гевгелија,
Гостивар (2 records), Дебар, Кавадарци, Кичево, Кочани, Охрид, Прилеп (2
records), Струга, Струмица, Тетово (КБ), Штип (КБ). With the 8 confirmed,
every town with a public general hospital now shows an emergency department
(confirmed or „непотврдено“). Duplicates to merge: Битола's published „ЈЗУ
Клиничка болница Битола“ (likely) and the unpublished „ЈЗУ Клиничка болница
„Д-р Трифун Пановски“ – Битола“ (confirmed) are the same hospital; Гостивар
and Прилеп also have two records each.

Gaps for staff: several key emergency departments (ТОАРИЛУЦ, Св. Наум, КБ
Битола „Д-р Трифун Пановски“) are still unpublished drafts. No `clinic` (on-duty) flags: the sources do not name on-duty
practices. No facility has confirmed `emergency_hours` yet.

### On-duty pharmacies

Source: ФЗОМ's monthly „Распоред на дежурни аптеки“ (an .xlsx per month for
every town, linked from https://fzo.org.mk/dezurni-apteki). Owner approved the
import on 2026-10-07 (terms: docs/third-party-assets.md).

```sh
php artisan import:on-duty-pharmacies --dry-run            # this month (+ next, if listed)
php artisan import:on-duty-pharmacies --month=2026-11
php artisan import:on-duty-pharmacies --file=/path/x.xlsx --month=2026-11
php artisan import:on-duty-pharmacies --force              # re-import an unchanged file
```

- **Fetching** (`OnDutyScheduleFetcher`): robots.txt first, our User-Agent
  with the contact (`IMPORT_USER_AGENT`, `IMPORT_CONTACT`), https only and
  only the page's host (`IMPORT_ON_DUTY_PHARMACIES_EXTRA_HOSTS` adds hosts),
  redirects within those hosts, a 5 MB cap, a 2 s pause, and a conditional
  GET (ETag / Last-Modified of the last run of that month, stored in its
  `import_runs.source_meta`). The month comes from the link text („… за 2026
  месец Октомври“) or the file name („…-10.2026.xlsx“). Raw files go to the
  private import disk (`imports/on-duty-pharmacies/`); only the newest 3 are
  kept (`IMPORT_ON_DUTY_PHARMACIES_KEEP_FILES`).
- **Parsing** (`OnDutyScheduleParser`, OpenSpout): the month heading (text
  „ОКТОМВРИ,2026“ or a date cell), town headings, then rows `№ | town |
  pharmacy | dates | phone | how the duty works`. Every date form seen in the
  2026 files is read (a date cell or its serial number, `01.10.2026`,
  `01-10-2026`, ranges with `-`/`—`/`до`/`од … до …`, `01.10-31.10.2026`,
  `29.12. до 01.01.2026`, `08/09-10-2026`, `1,2,3,4`, `6.14.22.30.`, a bare
  day, `4(недела)`, `… 2026 г.`, `18.01. 2026`, a missing dot before the
  year). The duty column gives the mode: `24/7` → all day, „по телефонски
  повик …“ → on call, times → hours (kept as written), anything else is taken
  for an address (Велес writes the street there). Only the **numbers** of the
  phone column are kept (some towns add the pharmacist's name). Towns: „СКОПЈЕ-
  КАРПОШ“ → Скопје + municipality Карпош; „М.БРОД“ → Македонски Брод.
- **Writing** (`OnDutyPharmacyImporter`): the month is replaced as a whole,
  one row per pharmacy and day (`pharmacy_duty_shifts`). A pharmacy is
  linked when its name without „ПЗУ“/„Аптека“/quotes/the town matches exactly
  one directory pharmacy in that town. **Nothing is created**: an unmatched
  or ambiguous pharmacy goes to the import review queue once (kind
  Unmatched, `on-duty-pharmacy:<town>:<name>`), and its rows still show on
  „Каде веднаш“ under ФЗОМ's name, without a profile link. **While the
  directory has no published pharmacy in a town** (today: none anywhere),
  that town's unmatched pharmacies are not queued — the owner works alone
  and 329 items a month would bury the queue — but listed in the run's
  report (`diff.csv`, action `unmatched`; counters `pharmacies_unmatched`,
  `pharmacies_unmatched_not_queued`) (integration decision 2026-10-07). A row whose date
  cannot be read goes to the queue too and is not guessed. A file with no
  readable rows fails and replaces nothing.
- **Schedule**: `40 6 1,15,28 * *` Skopje time — the 1st, a mid-month retry
  on the 15th (ФЗОМ published October's file on the 7th), and the 28th
  (next month's file usually appears in the last days). ON by default:
  `IMPORT_ON_DUTY_PHARMACIES` (the command) and
  `IMPORT_ON_DUTY_PHARMACIES_SCHEDULE` (the scheduler) switch it off. A month
  not listed yet ends as „not modified“ (`not_published`), not as a failure.
- **„Tonight“**: the schedule day in Skopje time; before 07:00 the previous
  day's (night duties end in the morning). `GET /urgent-care?city=…` returns
  `meta.on_duty_pharmacies = {available, date, source_url, items}` (items only
  with a city; all-day first, then hours, on call); `available = false` when
  the month is not imported → the placeholder with ФЗОМ's link.
  `GET /pharmacies/{slug}` returns `on_duty_today` → „Дежурна денес · …“ on
  the profile.

Real files, 2026-10-07 (dry runs; October applied on the preview copy):

| Month | Rows | Shift-days | Unreadable rows (first parser → final) |
|---|---|---|---|
| 2026-10 | 500 | 1116 | 0 → 0 |
| 2026-09 | 487 | 1080 | 16 → 0 |
| 2026-08 | 505 | 1116 | 17 → 0 |
| 2026-01 | 503 | 1115 | 31 → 1: Куманово, a date cell with the year 2206 (a typo in ФЗОМ's file), queued for staff |

32 towns, every day covered in October. **Matching: 0 of 329 pharmacies**
link to a profile, because the directory holds no pharmacies yet (the
pharmacy import is a separate, future source); since integration none of
them is queued (no published pharmacy in any town), all 329 are in the run
report. The schedule still shows them by ФЗОМ's name.

## 3. API

| Method | Path | Notes |
|---|---|---|
| `GET` | `/urgent-care?city=&type=ed\|ems\|clinic\|dental` | Published clinical facilities with any (or the given) service; ED first, then EMS, on-duty, dental; 24/7 first within each; at most 300. `city` matches like the directory (either script, „Скопје“ includes „Скопје - Карпош“). Evidence never leaves the API. `cache.public:60` |
| `GET` | `/urgent-care/cities` | `[{name, total, ed, ems, clinic, dental}]`, Skopje's municipalities merged into „Скопје“ |
| `POST` | `/feedback` | `{item, helpful}` |
| `POST` | `/feedback/reasons` | `{item, helpful, reasons: [..1–3]}` |
| `POST` | `/feedback/steps` | `{funnel, step, depth}` |

Item: `<namespace>:<slug>[:<slug>[:<slug>]]`, namespaces `guide`,
`urgent-care`, `guidance`, `page`; lowercase `[a-z0-9-]` (later segments also
`_`), ≤ 96 chars. Funnel: `guidance|urgent-care|page:<slug>[:<slug>]`. Step:
`[a-z0-9][a-z0-9_.:-]{0,63}`; depth 0–200. Reasons — after „Да“: `clear`,
`found-place`, `next-step`; after „Не“: `unclear`, `not-found`, `wrong-info`,
`outdated`, `not-relevant`. Anything else → 422. A well-formed key that names
nothing known is answered 204 and **not stored**: items must be a known guide
slug (`guide:`), `urgent-care:all` or a place id, or `guidance:<flow key |
global>:outcome:<one of the six levels>`; funnels must be `guidance:<known flow
key>` with a step of `start`, `outcome:<level>` or a node id of any version of
that flow (`App\Support\Feedback\FeedbackKeys`, `config/feedback.php`). The same vocabulary is in
`apps/api/config/feedback.php` and `apps/web/src/lib/feedback.ts`
(`FeedbackVocabularyParityTest`). Limiters: `api-feedback` 20/min + 200/day,
`api-funnel` 120/min + 1200/h, per network (HMAC, as `api-ux-events`).

## 4. Feedback — how to wire it (T-ENGINE / integrator)

Browser code never calls the API directly: it posts to the same-origin relay
`POST /api/feedback` (`{kind: "vote" | "reasons" | "step", …}`), which
rebuilds the message from the vocabulary, drops step counters unless the
request carries `X-Z360-Consent: statistics` (sent only after the visitor accepted
statistics), forwards the visitor's
address for the rate limit only, and always answers 204.

**Widget** — on an outcome screen:

```tsx
import { HelpfulFeedback } from "@/components/feedback/helpful-feedback";

<HelpfulFeedback item={`guidance:${flowSlug}:outcome:${level}`} />
```

(`flowSlug` lowercase-hyphen; `level` e.g. `urgent_same_day`.) An invalid key
renders nothing. The vote is sent immediately; reason chips are optional.

**Drop-off** — once per step the visitor reaches:

```ts
import { recordFunnelStep } from "@/lib/feedback";

recordFunnelStep(`guidance:${flowSlug}`, "start", 0);
recordFunnelStep(`guidance:${flowSlug}`, nodeKey, depth);        // e.g. "q-red-flags", 1
recordFunnelStep(`guidance:${flowSlug}`, `outcome:${level}`, n); // the end
```

Depth is the position on the visitor's path (0 = start). The report shows,
per funnel, each step's count and the share of the previous depth that got
that far. Node keys must match the step pattern (no Cyrillic, no spaces).
Nothing is sent with GPC/DNT on.

**Report** — admin → Platform → **Повратни информации** (`analytics.view`):
per item helpful %, counts and reasons, filterable by namespace and period;
per funnel the step table. Retention 730 days (`FEEDBACK_RETENTION_DAYS`),
`feedback:purge-old` daily 03:50.

Used today: every guide (`guide:<slug>`), „Каде веднаш“
(`urgent-care:<city-slug>` or `urgent-care:all`) and every guidance result
(`guidance:<flow>:outcome:<level>`, `guidance:global:…` for the shared
emergency/crisis outcome), with drop-off counted per flow (`start`, each
question id at its position, `outcome:<level>`) — `lib/guidance/next-steps.ts`.
Guidance outcomes link here too: `emergency_now` → `type=ed` under the call
buttons, `urgent_same_day` → all services (`dental` for a dental outcome),
first in „Најдете во именикот“, with the city typed on the result.

## 5. Guides

Content: `apps/web/src/content/guides/guides.tsx` (one entry per guide: title,
summary, `checked` date, sources, body). Every guide shows its sources, the
date they were read and „Информативно. Правилата се менуваат — пред да
постапите, проверете кај институцијата.“ Facts we could not confirm on an
official page carry an „непотврдено“ badge. Amounts are not copied: the
co-payment guide links to ФЗОМ's decision.

Sources read on 2026-10-07: Закон за здравственото осигурување (ФЗОМ
redaction 91/2026), ФЗОМ Правилник за правата (unofficial consolidated text
incl. 56/2026), ФЗОМ Одлука за учеството (unofficial consolidated 08/2025),
ФЗОМ „Права на осигурено лице“, „Постапка …“, „Обрасци“, Управа за
електронско здравство (FAQ, Мое здравје), mojtermin.mk, Закон за заштита на
правата на пациентите (consolidated to 150/15 + 190/2019, as linked by the
Ministry), Министерство за здравство contact pages, Лекарска комора „Суд на
честа“.

**Unverified** (marked on the page): whether a GP can be chosen online
without visiting the doctor; how a patient files with the Лекарска комора;
the Народен правобранител procedure (its site could not be read); the
co-payment annual cap in denars; any general exemption for pregnancy or
chronic illness (none found). 194 was confirmed only on a health centre's
site (ЗД Битола), 112 on the Crisis Management Centre's (described there
for crises; it says to also call emergency services when life is at risk).
ФЗОМ announced sick-leave changes on 2026-10-06: not described in the guides.

Re-check the sources and update `checked` at least every six months, and
whenever ФЗОМ publishes a new consolidated text.

## 6. Privacy

No personal data: counters only, no free text, no geolocation stored (the
distance sort runs in the browser; only the chosen city reaches the server).
`docs/data-inventory.md` and the privacy policy (anonymous use, retention,
OpenStreetMap) describe it.
