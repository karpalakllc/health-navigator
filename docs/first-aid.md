# Прва помош (first-aid guides)

Static, server-rendered first-aid guides in Macedonian at `/prva-pomos` (index)
and `/prva-pomos/{slug}` (one guide). Content lives in code, not the database:
`apps/web/src/content/first-aid/`.

**Every guide ships as a draft („Нацрт — чека лекарски преглед“) and is not public
until a clinician signs it off.** Clinician checklist:
[first-aid-review-checklist.md](./first-aid-review-checklist.md).

## Files

| Path (apps/web/src/…) | What |
|---|---|
| `content/first-aid/guides/*.ts` | The 19 guides (text, steps, sources, `published`, `review`) |
| `content/first-aid/index.ts` | Registry + **link contract** (slugs, anchors, topics, `firstAidHref`) |
| `content/first-aid/sources.ts` | Cited public sources with access dates |
| `content/first-aid/copy.ts` | Page chrome strings (kept here, not in `i18n/mk.ts`, so the section is reviewed as one unit) |
| `components/first-aid/*` | Article, index, 194/112 call links, print button, inline SVG illustrations, staff-preview gate, JSON-LD |
| `app/prva-pomos/*` | Routes; `print.css` strips the site chrome when printing |

## Publishing (published flag + clinician review)

A guide is public only when **both** are true (`isFirstAidGuidePublic`):

- `published: true`
- `review: { status: "reviewed", reviewedOn: "YYYY-MM-DD", reviewer?: "…", note?: "…" }`

Default for every guide: `published: false`, `review: { status: "draft" }`.
To publish after sign-off, edit the guide's file (record date, optional reviewer
name/registration, note) and set `published: true`; it is a code change, so it
goes through the normal review/merge.

While unpublished:

- the public gets a **404** on `/prva-pomos/{slug}`; the index shows a calm
  holding note (no 194/112 buttons or disclaimer in the index chrome — each
  guide carries its own „Прво повикајте 194“ / when to call) and is `noindex`;
- **staff** (API account role `admin` or `moderator`, signed in on the web) see
  every guide — this is the staff preview; no banner or draft label is shown
  (owner decision 2026-10-23); pages are `noindex`, no JSON-LD;
- nothing is linked: no footer link, no sitemap entry, `firstAidHref()` returns
  `null`, related-guide links to drafts are hidden from the public.

Once at least one guide is public: footer link „Прва помош“ (Ресурси column),
sitemap gets `/prva-pomos` + each published guide.

## Link contract (for guidance emergency outcomes and anything else)

Never hand-write `/prva-pomos/...` paths outside this section. Import from
`@/content/first-aid`:

```ts
import {
  firstAidHref,          // (slug, anchor?) => string | null  (null while unpublished)
  firstAidLinksForTopic, // (topic, anchor = "pomos") => {slug, title, href}[]  (published only)
  FIRST_AID_ANCHORS,
  FIRST_AID_TOPICS,
} from "@/content/first-aid";

// e.g. an emergency outcome for "not breathing":
const links = firstAidLinksForTopic("not-breathing"); // [] until reviewed — render nothing then
```

Render nothing when the result is `null`/empty — a draft link would 404.

Wired at integration (2026-10-07): a guidance `emergency_now` result lists
„Прва помош додека чекате“ from its flow's topics
(`FLOW_FIRST_AID_TOPICS` in `apps/web/src/lib/guidance/next-steps.ts`, e.g.
`chest-pain` → `chest-pain`, `burns` → `burn`); a crisis outcome or the
shared global emergency lists none. Nothing shows until a guide is published.

### Slugs (stable — never renamed or reused)

| Slug | Guide | Group |
|---|---|---|
| `kpr-vozrasni` | Оживување (КПР) кај возрасен | Не дише или се задавува |
| `kpr-dete` | Оживување (КПР) кај дете | 〃 |
| `kpr-bebe` | Оживување (КПР) кај бебе до 1 година | 〃 |
| `zadavuvanje` | Задавување кај возрасен или дете над 1 година | 〃 |
| `zadavuvanje-bebe` | Задавување кај бебе до 1 година | 〃 |
| `mozocen-udar` | Мозочен удар | Ненадејни симптоми |
| `srcev-udar` | Срцев удар (инфаркт) | 〃 |
| `epileptichen-napad` | Епилептичен напад (грчеви) | 〃 |
| `anafilaksa` | Тешка алергиска реакција (анафилакса) | 〃 |
| `nesvestica` | Несвестица | 〃 |
| `nizok-sheker` | Низок шеќер во крвта (хипогликемија) | 〃 |
| `silno-krvarenje` | Силно крвавење | Повреди |
| `izgorenici` | Изгореници и попарувања | 〃 |
| `povreda-na-glava` | Повреда на главата — опасни знаци | 〃 |
| `krvarenje-od-nos` | Крвавење од нос | 〃 |
| `istegnuvanje` | Истегнување и извртување на зглоб | 〃 |
| `truenje` | Труење | Труење, жештина и студ |
| `toploten-udar` | Топлотен удар | 〃 |
| `hipotermija` | Потладување (хипотермија) | 〃 |

### Anchors on every guide page

| Anchor | Section |
|---|---|
| `#povikajte-194` | „Прво повикајте 194“ box (or „Кога да повикате 194“) |
| `#prepoznavanje` | Како да препознаете |
| `#pomos` | Што да направите (numbered steps) — default for topic links |
| `#ne-pravete` | Што да НЕ правите |
| `#koga-194` | Повикајте 194 (или повторно) ако… |
| `#izvori` | Sources + review status |

Step sections also have their own ids (e.g. `kpr-vozrasni#samo-so-race`,
`kpr-vozrasni#so-vdishuvanja`, `zadavuvanje#teshko`, `truenje#gas`); they are
stable too. Tests (`first-aid.test.ts`, `first-aid-pages.test.tsx`) enforce
unique ASCII ids and that every anchor exists exactly once per page.

### Topics → guides (`FIRST_AID_TOPICS`)

`not-breathing` → kpr-vozrasni, kpr-dete, kpr-bebe · `choking` → zadavuvanje,
zadavuvanje-bebe · `stroke` · `chest-pain` → srcev-udar, kpr-vozrasni ·
`seizure` · `anaphylaxis` · `fainting` · `low-blood-sugar` · `bleeding` · `burn`
· `head-injury` · `nosebleed` · `sprain` · `poisoning` · `heat` · `cold`.

The guidance engine (T-ENGINE) owns the decision of which topic an emergency
outcome maps to; this package does not edit guidance files.

## Delivery choices

- **Server-rendered, minimal JS**: guides are server components; the only
  client script of their own is the „Печати“ button (`window.print()`). The
  site shell's scripts still load (shared layout).
- **Print**: `app/prva-pomos/print.css` hides header/footer/nav/tab bar
  (keyed off `[data-first-aid-print]`, so it does nothing on other pages), tel
  buttons print as plain „194“, source URLs print in full.
- **Offline**: no service worker. The CSP would allow one (`worker-src 'self'`),
  but every page is rendered per request with the signed-in member's header
  (name/avatar) and staff previews; caching that HTML on a shared device would
  leak it, and a site-wide worker scope is out of proportion for a local-only
  app. Instead the pages are small and printable, and say so („испечатете ја или
  зачувајте ја страницата однапред“). Revisit with a dedicated, cookie-free
  static export of published guides if hosting is chosen.
- **Accessibility**: one h1, h2 per section, real `<ol>` steps with visible
  numbers, text ≥ 18px (steps 20–22px), 194/112 as real `tel:` links whose
  text contains the number, warnings marked with an icon *and* words (never
  colour alone), illustrations decorative (`aria-hidden`) because each step
  states in words what it shows. axe runs in the vitest suite per guide.
- **Illustrations**: original inline SVG in the D2a spot style (3px ink line,
  cream/apricot fills, coral only for the pressing hand/heart). No photos, no
  third-party images.
- **SEO**: `pageMetadata` (canonical, OG); drafts `noindex`. JSON-LD for
  published guides only: schema.org `MedicalWebPage` (+ `citation`,
  `lastReviewed`, `reviewedBy` once signed off) and `BreadcrumbList`. **Not
  HowTo**: Google retired HowTo rich results in 2023 and no longer lists the
  type among supported structured-data features (checked 2026-10-07 on
  developers.google.com/search/docs/appearance/structured-data/search-gallery).

## Sources and unverified facts

Sources were read on 2026-10-07 and are cited per guide (`sources.ts`): ERC
Guidelines 2025 (erc.edu landing page only — the guideline PDFs/summaries were
not reachable), St John Ambulance CPR and choking (the NHS „first aid“ pages now
redirect there), and the NHS condition pages for stroke, heart attack,
anaphylaxis, burns, head injury, poisoning, fainting, hypoglycaemia,
heatstroke, hypothermia, nosebleed, sprains, seizures, cuts and grazes. All text
is original wording.

Could **not** be opened during writing (not cited; the clinician should
cross-check against them): IFRC International First Aid, Resuscitation and
Education Guidelines (403), Црвен крст на Македонија public materials (site
unreachable), WHO.

Open / unverified — see the checklist:

1. **Toxicology clinic phone number** (Универзитетска клиника за токсикологија,
   Скопје, ул. Мајка Тереза бр. 17): **02 3147 168**, verified 2026-10-23 on the
   Ministry of Health list „Установи од секундарна и терцијарна ЗЗ“
   (https://zdravstvo.gov.mk/mk-MK/ustanovi/ustanovi-od-sekundarna-i-tercijalna-zz,
   which also lists 02 3237 504). The page does **not** say the line is staffed
   24/7 or is a poison-information line, so the guide claims neither: `truenje`
   shows it after 194/112 in the call box as „Совет при труење: Клиника за
   токсикологија, Скопје“ (`phones`, rendered by `FirstAidPhoneLinks` in
   `components/first-aid/call-links.tsx`) and keeps „Повикајте 194“ first. An
   earlier unverified candidate number was dropped.
2. **Stroke mnemonic**: no officially used Macedonian mnemonic was found, so
   the guide lists the signs (лице, рака, говор, време) in plain sentences
   without inventing an acronym.
3. ERC 2025 details not seen first-hand: infant compression technique (two
   fingers vs. two-thumb encircling), chest thrusts for pregnant/obese choking
   adults, tourniquet wording, cold-water immersion for heat stroke.
4. 194 (ambulance) and 112 (general emergency) are the numbers already used
   across the site.

### Phone numbers besides 194/112 (`phones`)

A guide may list further numbers in `phones` (`{ number, label }`); they render
as tap-to-call links after 194 and 112, in ink rather than emergency red. A
number written with the trunk prefix (02 …, 07…) dials in international form
(`tel:+3892…`) so it also works from a foreign SIM; the text keeps the local
form. Only
numbers verified on an official page, with the page recorded here; the label
says who answers and never claims hours the source does not state. Vitest
(`first-aid.test.ts`) checks the format and that 194/112 are not repeated.

| Guide | Number | Label | Source (checked) |
|---|---|---|---|
| `truenje` | 02 3147 168 | Совет при труење: Клиника за токсикологија, Скопје | zdravstvo.gov.mk, Установи од секундарна и терцијарна ЗЗ (2026-10-23) |

### Changes 2026-10-23 (safety fixes, pending clinician review)

- `zadavuvanje`, `zadavuvanje-bebe`: the call box now says call 194 at once when
  the choking is severe (someone else calls) or, alone, after the **first**
  unsuccessful round of 5 back blows and 5 thrusts (RCUK 2025 First aid: „If
  choking has not been relieved after five abdominal thrusts, call 999“); the
  former trigger was „after three rounds“. Steps say „со вклучен звучник“; the
  adult guide adds when to see a doctor after the object comes out; the infant
  guide says what to do when a cough becomes weak or silent.
- `povreda-na-glava`: fall from height, traffic collision or neck pain → call
  194 and do not move the person; neck pain, tingling/weakness and a dangerous
  mechanism added to the 194 list; „повраќа повеќе пати“ → „повраќал по
  повредата“ (NHS: being sick since the injury); „постар човек“ → 65 or older.
- `truenje`: toxicology clinic number (above); gas: no flames or switches,
  switch the appliance off if safe.
