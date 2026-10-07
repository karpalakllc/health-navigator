# Profile verification: rules and evidence

How doctor, facility and pharmacy profiles become „Верификуван“ (doctors)
or „Верификувана“ (facilities and pharmacies, short form without a noun —
owner's decision, 2026-10-07) — automatically, from the
import evidence — and what is left for a person. The badge, its wording and
the staff Verify / Unverify actions are described with the API
(`verification` on the doctor, facility and pharmacy resources); the
columns are written only by `App\Support\Verification\VerificationWriter`.

Code: `apps/api/app/Support/Verification/Engine/**` (the engine),
`apps/api/app/Support/Licences/**` (licence matching),
`apps/api/app/Console/Commands/AdjudicateCommand.php` (`import:adjudicate`).
Runbook of the imports themselves: [`data-import.md`](data-import.md).

## 1. The principle: precision over recall

A profile is verified only when **two independent sources agree** on the
person or the institution, or when a person checked the identity. One
source alone is not enough — with one exception the owner decided
(2026-10): a **dentist** with a current ФЗОМ contract (§2, `fzom_dentist`),
because there is no public dental licence list to agree with it. Nothing
is guessed:
whenever the evidence could fit two people, the profile stays unverified.
An unverified profile is not wrong; it is not confirmed yet. The owner
works alone, so the engine decides everything it can and raises only the
cases where one human decision changes the outcome — grouped, so one click
settles many profiles.

The sources and what each one proves:

| Source | Proves | Does not prove |
|---|---|---|
| ФЗОМ „Шифрарник на лекари“ (`import:fzom`) | the person has a contract at this institution today (name, institution, specialty of the contract); the institution exists (tax number, name, town) | a valid licence; dentists' specialties beyond "dentist" |
| Лекарска комора licence list (`import:komora-licences`) | a person of this name holds a valid licence in this specialty | where they work; which of two namesakes is ours |
| The institution's own staff page (`import:institutions-json`) | the institution lists this person (name, often specialty) | anything, if the site is compromised or stale (§5) |
| Staff | identity checked outside the platform (owner claim, staff Verify) | — |

## 2. Doctors: when the engine verifies

Rules are tried in this order; the first that holds wins. Evidence stored
with the verification (internal, `verification_reasons.evidence`) is ids
and rule names only — never a licence number or a ФЗО facsimile.

| Rule (`evidence.rule`) | Basis shown | Evidence required (all of it) |
|---|---|---|
| `owner_claim` | `owner_claim` | Staff linked a member account to the profile (`owner_user_id` and `owner_linked_at` set by `AssignDoctorOwner`, after checking the person outside the platform). |
| `fzom_licence` | `official_registers` | 0. the profile has at least one specialty (§2a); 1. ФЗОМ lists the doctor in the latest complete snapshot that was applied (source record seen by that run, `import_missing_runs = 0`); 2. the attached Комора licence is on the latest list (`missing_since` empty) and valid today; 3. the licence holder's name is the profile name (normalised, either word order); 4. the licence specialty fits the profile's specialties (licence specialty mapping, §6); 5. no other row on the list with the same name also fits the profile. |
| `fzom_licence` (namesakes) | `official_registers` | ФЗОМ as above, no licence attached, and the list holds **several** valid licences of this name that fit the profile — at least as many as there are profiles of that name without a licence. Whichever is theirs, the ФЗОМ doctor holds one. No number is attached (`namesake_licences` = how many). |
| `fzom_dentist` | `official_registers` (public label „Регистар на ФЗОМ“) | **Dentists only** (every specialty of the profile is a dental one, `stomatologija*`): ФЗОМ lists them in the latest complete snapshot as above. Nothing else is needed (owner's decision, 2026-10: there is no public dental licence list). A Комора licence attached to the profile and lapsed (expired or off the list) still blocks it. A dentist who drops out of ФЗОМ loses the badge on the next run (`source_removed`; a published one raises `verification_lost`). |
| `website_licence` | `licence_and_website` | 1. The institution's own staff page lists the doctor (website import, research confidence „high“, the profile still linked to that institution, the site not flagged — §5); 2. the attached licence is on the list, valid, the profile's name and specialty as above; 3. **no other row on the whole list has this name** (stricter than ФЗОМ: a staff page is a weaker anchor than a contract). |
| `fzom_website` | `website_and_register` | ФЗОМ current as above **and** the staff page of one of the **same** institutions the ФЗОМ contract names lists the doctor (high confidence, unflagged, still linked), and the page states no specialty that contradicts ФЗОМ's (a page wording nobody has mapped yet is not a contradiction). |

Everything else is **unverified**, with the first reason that applies:

| Reason | Meaning | A question for staff? |
|---|---|---|
| `suppressed` | Removed on objection or deleted; never verified automatically | no |
| `licence_off_list` | The attached licence is no longer on the latest complete Комора list | only if the profile was public and verified (§4) |
| `licence_expired` | The attached licence has expired | as above |
| `stale_register` | ФЗОМ was not imported, nor confirmed unchanged by a 304, within `IMPORT_FZOM_MAX_AGE_DAYS` (45): nothing from ФЗОМ is current. `import:adjudicate --report` warns and the import alert inbox gets „Застарен регистар“ | as above |
| `source_removed` | No longer in the latest ФЗОМ snapshot (facility: no longer in the register) | as above |
| `website_removed` | The latest import of the institution's site no longer lists the doctor (and ФЗОМ does not) | as above |
| `website_outdated` | No import of the site listed the doctor within `IMPORT_WEBSITE_MAX_AGE_DAYS` (180) | as above |
| `licence_name_differs` | The licence holder's name is not the profile's (renamed by hand?) | no |
| `no_specialty_unverifiable` | The profile has no specialty; a general doctor's licence would fit it on the name alone (§2a) | no — the ФЗОМ „new“ item already warns „check they are doctors“; add the specialty or verify by hand |
| `no_specialty` | The profile has no specialty to compare with a specialist licence | no — map the page's wording (Specialty aliases), then it is re-evaluated |
| `specialty_mismatch` | The licence specialty does not fit the profile's | one item **per wording pair** when it is a mapping question (§3) |
| `ambiguous_name` | Another doctor of the same name could hold the licence | the licence's own „ambiguous“ item (Комора) |
| `stale_source` | Only a website flagged as compromised or stale lists the doctor | one item **per site** when trusting it would verify someone (§3) |
| `low_confidence_source` | Only a staff-page entry the research marked uncertain lists the doctor | no |
| `sources_disagree` | ФЗОМ and the staff page name different institutions, or contradicting specialties | no |
| `dentist_single_source` | Dentist known only from a staff page, not from ФЗОМ | no |
| `fzom_no_licence` | Current in ФЗОМ, but the Комора list has no licence of this name | no — may be **published unverified** (§8) |
| `no_licence` | Known from a staff page only, and the Комора list has no licence of this name | no |
| `no_import_evidence` | Entered by hand, no source evidence: staff verify it | no |

„No licence“ and one-source cases are **not** review items: there is
nothing a person could decide from the queue — the evidence is simply
absent. They remain drafts until a later import brings a second source, or
staff verify them individually — except `fzom_no_licence` drafts, which the
owner decided may go public unverified (§8).

### 2a. Profiles without a specialty

A ФЗОМ draft without any specialty is mostly laboratory staff; its „new“
item says to check they are doctors. A general doctor's licence „fits“ such
a profile (§6), but only by the name, so no licence-based rule
(`fzom_licence`, its namesake variant, `website_licence`) verifies it:
reason `no_specialty_unverifiable`. The bulk publish sets skip every draft
the ФЗОМ import marked `no_specialty` (§8).

### 2b. Freshness of the evidence

- **ФЗОМ:** "current" means listed by the latest complete applied
  snapshot (without a complete one, the latest successful apply of any
  kind). If ФЗОМ was not imported or confirmed unchanged (304) within
  `IMPORT_FZOM_MAX_AGE_DAYS` (45), nothing from ФЗОМ counts:
  `stale_register` for doctors and facilities, a warning in `--report` and
  a mail to the import alert inbox.
- **Websites:** every website import stamps `last_seen_at` on each
  institution and staff-page entry it lists, changed or not. An entry older
  than its institution's record was missing from the latest import of that
  site → `website_removed`. An entry no import listed within
  `IMPORT_WEBSITE_MAX_AGE_DAYS` (180) → `website_outdated`. Entries stored
  before this stamp existed are refreshed by the next import of their slice.

## 3. Facilities and pharmacies

| Rule | Basis | Evidence |
|---|---|---|
| `fzom_register` | `official_registers` | The ФЗОМ register lists the institution in the latest complete snapshot, and the profile still carries the register's tax number (ЕДБ, or the ФЗО code for an institution without one), the same name words (case, quotes and punctuation aside) and the same town. |

Unverified: `register_mismatch` (in the register, but name, town or tax
number differ — an item when the profile is public), `stale_register`, `source_removed`,
`not_in_register` (known from its website only), `no_pharmacy_register`
(pharmacies: ФЗОМ's pharmacy contracts are deliberately not imported and no
other official pharmacy register is imported yet, so pharmacies wait for
staff or a future register import), `no_import_evidence`.

## 4. What reaches the review queue

Only „uncertain“ items (kind **Uncertain**, source `verification`, in
**Data import → Import review**), ranked by `priority` (published profiles
and big groups first):

| Item | One decision settles | Action |
|---|---|---|
| `specialty_mapping` — „Licence specialty X never fits Y: N doctors“ | every doctor behind the wording pair (name and workplace agree, the specialty mapping does not). Only specialist licences: a general doctor's licence on a specialist profile contradicts the profile (often a doctor in specialisation) and mapping it would be wrong, so it stays unverified without an item. | Add the compatibility in **Licence specialty mapping**, or Dismiss (they stay unverified). The next run re-matches and verifies. |
| `flagged_source` — „Website flagged as compromised/stale: … N doctors would be verified“ | every doctor that site's staff list would verify | **Trust this website** (after checking the site is current and clean) or Dismiss |
| `verification_lost` | a **published** profile lost its verification (licence expired or off the list, gone from ФЗОМ or the staff page, a stale source). Raised again on every run while the profile stays unverified, so it stays open until the verification returns (closed `no_longer_applies`) or staff act; a dismissal sticks while nothing changes | Hide it, verify by hand after checking, or Dismiss |
| `register_mismatch` | a **published** facility no longer matches the register | Fix the profile or verify by hand |

Komora „ambiguous“ licence items (several profiles fit one licence) are
ranked just above the ordinary queue; those whose profiles all got
verified through their namesakes' licences are dismissed by the engine
(`verified_without_number`) and stay so while nothing changes.

**Not** in the queue any more: Комора licence rows of people who have no
profile here (`no_match`, most of the list) and per-licence specialty
mismatches. Both stay in the `komora_licences` staging only (**Data import →
Licences (Комора)**); the engine reports mismatches per wording pair. Items
the engine raised close themselves (`no_longer_applies`) once the case is
settled.

## 5. Website warnings

The research notes of an institution (`notes` in `institutions.json`) are
scanned when the slice is imported (`App\Support\Import\Website\SourceNotes`):
injected spam / casino / „compromised“ → `compromised`; „stale“, „dated“,
„last news 2019“, „застарен“ → `stale`. Only the flags and the sentence
behind them are kept on the facility's source record, not the notes. A
flagged site's staff list does not count as evidence until staff trust it
(one click per site; the decision sticks). Keyword matching errs towards
flagging: a false flag costs one click, a missed one a wrong badge.

## 6. Licence matching (extended for verification)

`import:komora-licences` matches each licence row to a profile (name +
specialty, never a guess — [`data-import.md`](data-import.md) §5). For
verification it is extended:

- **Website-only drafts** are matched too, as *fallback* candidates: only
  for a name no ФЗОМ profile carries. If ФЗОМ profiles have the name but
  none fits while a website draft does, the row is *ambiguous* (two people,
  or one person twice). `KOMORA_MATCH_FALLBACK_SOURCES` (default `website`;
  empty turns it off).
- **A general doctor's licence** („доктор на медицина во ПЗЗ“, group
  `KOMORA_GENERAL_GROUP`, default `opsta-medicina`) fits a ФЗОМ profile with
  no specialty at all — both say "no specialisation". Not a website draft
  without a specialty: its page may state one nobody has mapped yet.
- **Re-matching**: every engine run first re-decides the unattached rows of
  the current list against today's profiles and mapping, so a new website
  draft or a mapping fix is picked up without waiting for the next list.

## 7. When it runs

| Trigger | |
|---|---|
| After every successful import apply | ФЗОМ and Комора (listener `VerifyAfterImportRun` on `ImportRunFinished`), websites (the command). `IMPORT_VERIFY_AFTER_IMPORT=false` turns it off. A busy engine is skipped; a failing one never fails the import. |
| Nightly, 05:50 | `import:adjudicate` (always on: it only reads and writes the database). An expired licence loses its badge on the day it expires. |
| By hand | `php artisan import:adjudicate` (apply), `--dry-run` (decide and count, write nothing; the licence re-match is skipped), `--report` (the owner's summary: verified by rule, unverified by reason, open questions by reason, drafts ready to publish, a stale-ФЗОМ warning — counts only, never names; like `--dry-run` it writes nothing to profiles but records its run as a dry-run `import_runs` row). |

Each run is an `import_runs` row with source `verification` and its counts
(**Data import → Import runs**). Status changes of drafts are not written to
the audit log one by one (like imports); changes of published profiles are
(log `verification`). Staff decisions are never overridden: an automatic
call on a profile staff decided returns `staff_decision_kept`. The writer
re-reads the verification columns with the row locked before every
automatic write, so a staff decision made while a run is in progress wins
too. **Release** hands it back to the engine.

## 8. Publishing verified drafts

The engine never publishes on its own unless asked. Both sets below take
only **never-published** drafts (`published_at` empty: publishing by any
path — the edit form, a table action, the queue, auto-publish — stamps it
and closes the profile's „new“ items, and unpublishing keeps it), so a
profile staff unpublished is never re-published in bulk. Both skip drafts
with **any other open review item** (possible duplicate, conflict, missing,
uncertain…) and drafts the ФЗОМ import marked `no_specialty`. A click
publishes no more than the modal counted (the highest item id when it
opened).

- **„Објави ги сите верификувани“** (header action in Import review,
  `imports.manage`): shows how many verified hidden drafts there are and a
  random sample of 20 to glance at, then publishes them all through the
  normal publish action (suppressed doctors refused, hidden imported
  specialties published along, their „new“ items closed). Auto-published
  drafts are sent to the search index after the run.
- `IMPORT_AUTO_PUBLISH_VERIFIED=true` (default `false`): each run publishes
  the drafts **it newly verified** — not the backlog, which stays for the
  bulk action.

Owner's decision (2026-10): doctors ФЗОМ lists today whose name has **no
licence on the Комора list** are published but stay „Неверификуван“; they
are verified automatically once a licence (or a staff page of the same
institution) appears.

- **„Објави ги и неверификуваните од ФЗОМ“** (next to the first action,
  `imports.manage`): count and a random sample of 20, then publishes the
  open „new“ drafts whose engine reason is `fzom_no_licence` — set by the
  engine, never a staff decision — and that have **no other open review
  item** (conflict, missing, possible duplicate, uncertain…). By
  construction it never takes `ambiguous_name`, `sources_disagree`,
  `specialty_mismatch`, `no_specialty`, a lapsed licence, or website-only
  drafts (`no_licence`, `stale_source`, `low_confidence_source`).
- `IMPORT_AUTO_PUBLISH_FZOM_UNVERIFIED=true` (default `false`, separate from
  the flag above): each run publishes the drafts that **entered** that set
  in this run (after the run's review items are raised, so a new blocking
  item keeps a draft hidden); the backlog stays for the bulk action.
- `import:adjudicate --report` prints both counts.

## 9. Measured

Synthetic fixtures: `tests/Feature/Verification/VerificationEngineTest.php`
(every rule and reason, the review items, staff decisions, dry run,
auto-publish), `tests/Feature/Licences/*` (matching), and the Filament
queue in `tests/Feature/Filament/VerificationQueueTest.php`.

Real data, local dry evaluation (scratch copy of the database, 2026-10-07;
ФЗОМ files of 2026-10-06, Комора list of 02.07.2026, the five website
research slices; the engine running after every import as in production;
counts only):

| | Count |
|---|---|
| Doctors verified: `fzom_licence` | 4,675 |
| Doctors verified: `website_licence` | 387 |
| Doctors verified: `fzom_website` | 21 |
| Facilities verified: `fzom_register` | 2,623 (every ФЗОМ institution) |
| Doctors unverified: `dentist_single_source` (measured before `fzom_dentist`: almost all of these are ФЗОМ dentists, now verified by it) | 1,698 |
| … `no_licence` (measured before the split: ФЗОМ doctors now report `fzom_no_licence`, website-only drafts `no_licence`) | 1,044 |
| … `stale_source` | 328 |
| … `no_specialty` | 180 |
| … `low_confidence_source` | 118 |
| … `specialty_mismatch` | 62 |
| … `licence_expired` | 41 |
| … `ambiguous_name` | 35 |
| … `sources_disagree` | 32 |
| … `no_import_evidence` (hand-made demo profiles) | 16 |
| Facilities unverified: `not_in_register` / `no_import_evidence` | 153 / 8 |
| Pharmacies unverified: `no_pharmacy_register` | 3 |
| **Uncertain items for the owner**: `specialty_mapping` / `flagged_source` | **10 / 1** |
| Комора `ambiguous` licence items left open (after namesake settling) | 17 |
| Other open import items (not verification): unmapped website specialty wordings / website facility matches | 133 / 4 |
| Licence rows kept as staging only (`no_match`), formerly one review item each | 3,926 |
| Verified drafts ready for „Објави ги сите верификувани“ | 7,706 of 11,397 drafts |

## 10. Known limits

- Pharmacies cannot be verified automatically until an official pharmacy
  register is imported (ФЗОМ pharmacy contracts are excluded on purpose);
  staff verify them. The public copy names only ФЗОМ and the Лекарска
  комора.
- The Ministry's facility register is not imported; facilities are checked
  against ФЗОМ only.
- An `owner_claim` verification stays when an attached licence later
  expires: the identity was checked by a person (owner's choice; staff can
  Unverify).
- The nightly run (05:50) may read a ФЗОМ import (Monday 05:30) still being
  applied; the next run corrects it (at most a day of staleness).
- Website warnings come from keyword matching on free-text research notes.
- A staff page whose specialty wording nobody has mapped yet does not
  contradict ФЗОМ (rule `fzom_website`): the identity rests on the name at
  the same institution in two sources.
