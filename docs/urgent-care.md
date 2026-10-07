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

Facility form → **Urgent care („Каде веднаш“)**: the four services, 24/7,
hours, direct line, public note, „Confirmed by staff“, and the import
evidence (read only). Facilities list → filter **Urgent care**: per service,
any, or **„Import evidence, not confirmed by staff“** — the staff to-do list.

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

Gaps for staff: most general hospitals (Прилеп, Охрид, Струмица, Тетово,
Гостивар, Штип, Велес, Кавадарци, Кочани, Гевгелија, Дебар, Кичево …) have no
named emergency unit in the sources and are only candidates; several key
emergency departments (ТОАРИЛУЦ, Св. Наум, КБ Битола) are still unpublished
drafts. No `clinic` (on-duty) flags: the sources do not name on-duty
practices. No facility has confirmed `emergency_hours` yet.

### On-duty pharmacies

No data field exists. The page shows „Податоците за дежурни аптеки наскоро“
and links to ФЗОМ's monthly schedule (https://fzo.org.mk/dezurni-apteki, an
.xlsx per month for every town, with columns town / pharmacy / date / phone /
how the duty works). `GET /urgent-care` returns
`meta.on_duty_pharmacies.available = false`. **Owner decision needed:** may
we import that schedule (licence/terms of the file), and how often.

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
`outdated`, `not-relevant`. Anything else → 422. The same vocabulary is in
`apps/api/config/feedback.php` and `apps/web/src/lib/feedback.ts`
(`FeedbackVocabularyParityTest`). Limiters: `api-feedback` 20/min + 200/day,
`api-funnel` 120/min + 1200/h, per network (HMAC, as `api-ux-events`).

## 4. Feedback — how to wire it (T-ENGINE / integrator)

Browser code never calls the API directly: it posts to the same-origin relay
`POST /api/feedback` (`{kind: "vote" | "reasons" | "step", …}`), which
rebuilds the message from the vocabulary, drops step counters when the
browser sends Global Privacy Control / Do Not Track, forwards the visitor's
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

Used today: every guide (`guide:<slug>`) and „Каде веднаш“
(`urgent-care:<city-slug>` or `urgent-care:all`).

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
