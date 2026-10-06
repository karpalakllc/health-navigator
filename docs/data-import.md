# Directory data: imports, corrections and objections (runbook)

How doctor and facility data gets into the directory from public sources, how
staff review and publish it, how to undo a bad run, and how the public asks for
a correction or a removal. Background and legal reasoning:
[`docs/legal/research-memo.md`](legal/research-memo.md) §2.1 and §2.1.1.
Personal data held: [`docs/data-inventory.md`](data-inventory.md).

Code: `apps/api/app/Support/Import/**` (import core, ФЗОМ, websites),
`apps/api/app/Support/Licences/**` (Лекарска комора), `apps/api/app/Support/DataOps/**`
(schedule, alerts). Admin: the **Data import** group in `/admin`, and
**Directory → Corrections**. The command code is authoritative:
`php artisan <command> --help` lists every option.

---

## 1. Sources

| Source | Command | What we take | What we never take | Cadence |
|---|---|---|---|---|
| ФЗОМ „Шифрарник на лекари“ (two XML files: primary care, specialist/hospital) | `import:fzom` | Doctor first and last name, specialty, institution (name, ФЗО codes, tax number), work unit, address, town; the facsimile number as an **internal** matching key | Pharmacy contracts, nurses' names (`ClenNaTim`), absence reasons and validity statuses (`PricinaOtsustvo`, `*StatusValidnostID`), who substitutes for whom (`RedovnaZamena`, `VZamena*`), people whose every specialty is a non-physician profession, any phone or e-mail of a person | Weekly (Monday 05:30), once enabled |
| Лекарска комора „Листа на доктори со важечки лиценци“ (a handful of PDFs) | `import:komora-licences` | Name, licensed specialty, licence expiry, licence number (**internal** only: matching and dedup) | Anything not in the list; the number and expiry date are never shown or exported publicly | Monthly (3rd, 06:00), once enabled; the Комора republishes about every four months |
| Institutions' own websites (a research slice prepared outside the app) | `import:institutions-json` | Facility contact details, logo, cover photos (each with its source URL); names and specialties of listed physicians and dentists | Photos of people, nurses and other staff | By hand only |

Dentists come from ФЗОМ (and websites) only: there is no public dental licence
list, so they never get a licence status. Both downloaders identify themselves
with a User-Agent and contact address (`IMPORT_USER_AGENT`, `IMPORT_CONTACT` —
**set a monitored address before the first real run**), honour `robots.txt`,
pause between requests and send conditional requests (`If-Modified-Since` /
`ETag`). Downloads are kept on the **private** disk, never the public media
disk and never in git, the newest three runs per source only (failed runs
included; a dry run keeps none): `storage/app/private/imports/snapshots/fzom/…`
(`IMPORT_SNAPSHOT_RETENTION`) and `storage/app/private/imports/komora/…`
(`KOMORA_KEEP_SNAPSHOTS`). A ФЗОМ snapshot is **minimised** before it is
written: only the elements the import reads are kept, and nurses' names,
absence reasons and status, substitutions and pharmacy rows never reach the
disk. The run records the original file's sha256 and size. An older folder
without the minimised marker (`.minimised`) is deleted on the next run.

## 2. Guarantees every import keeps

- **Nothing goes public by itself.** New doctors and facilities are hidden
  drafts (`is_published = false`). Staff publish them from the review queue.
  An import never publishes, unpublishes or deletes a profile.
- **Dry run first.** `--dry-run` runs exactly the same decisions and writes
  nothing to the directory (ФЗОМ and websites: one transaction, rolled back;
  Комора: decide and count only). The run record and its counts are kept, so
  you see what an apply would do. A dry run stores no image files.
- **Idempotent.** Each source record is stored with a hash
  (`source_records`); a record that did not change is skipped. Running the
  same files twice changes nothing the second time.
- **Batches.** A ФЗОМ apply commits every batch (`IMPORT_BATCH_SIZE`,
  default 250) in its own transaction. A failed run keeps the finished
  batches and can simply be run again.
- **Provenance per field** (`field_provenance`): which source wrote the
  value, the value it wrote, when, the source record and, for web sources,
  the page URL.
- **Staff edits win.** If a field's current value is not what the import
  last wrote, somebody changed it: the import does not overwrite it and
  raises a **conflict** instead. A link (specialty, workplace) that staff
  removed is not added back.
- **Locks.** On a doctor or facility edit page, **Import locks** lists the
  fields with their source; a locked field (the licence is the field
  `licence`) is never written by any import.
- **Missing ≠ deleted.** A record absent from two consecutive complete
  ФЗОМ snapshots (`IMPORT_MISSING_AFTER_RUNS`) is queued as **missing**.
  Staff decide (hide or keep). It is queued once, when it crosses the
  threshold; **Dismiss** sticks while the record stays away (it is queued
  again only after it came back and left again). Likewise a dismissed
  ambiguous match or unmapped wording stays dismissed while the source row
  is the same, and the run counts (`review_*`, the alerts) count only new
  items. A partial run (`--pzz` or `--spec` alone)
  updates what its file lists and never counts anyone as missing.
- **Safety stop.** A complete ФЗОМ apply in which more than 20 %
  (`IMPORT_MAX_MISSING_RATIO`) of the doctors of the previous complete
  snapshot are absent aborts before writing anything (no aliases,
  specialties or review items either) — that is what a truncated source
  file looks like. Ordinary turnover does not add up: the baseline is the
  previous snapshot, not every doctor ever seen.
- **No search or audit-log noise.** Imports do not touch the search index
  (drafts are not searchable anyway) or write one activity-log entry per
  record; the run, its diff and the provenance are the record. Publishing
  goes through the normal model events (search index, activity log).

## 3. Running an import by hand

Always start with a dry run.

```sh
php artisan import:fzom --dry-run
php artisan import:fzom                       # apply
php artisan import:fzom --pzz=/path/a.xml --spec=/path/b.xml --dry-run   # local files (both = complete)
php artisan import:fzom --pzz=/path/a.xml --dry-run   # one file = partial run, never marks missing
php artisan import:fzom --force               # import although the source answered 304

php artisan import:komora-licences --dry-run
php artisan import:komora-licences            # apply
php artisan import:komora-licences --file=/path/А-В.pdf --file=/path/Г-Ж.pdf --list-date=01.09.2026 --dry-run
php artisan import:komora-licences --force    # process the list although no file changed

php artisan import:institutions-json /path/<slice>/institutions.json --dry-run
php artisan import:institutions-json /path/<slice>/institutions.json
```

`import:komora-licences --file` reads local PDFs; such a partial list never
marks licences missing unless you add `--complete` (the files are the whole
list). Without `--list-date` the date is read from the file names.

Each run — every source, dry run or apply — writes an `import_runs` row
(source, mode, status, counts, error); ФЗОМ and website runs also write a diff
summary CSV on the private import disk, and licence review items link back to
their Комора run. Both are under **Data import → Import runs** (`imports.view`).

Read the counts before applying:

- ФЗОМ: `rows_read`, `doctors_in_source`, `facilities_in_source` — roughly
  stable week to week; a sudden drop means a truncated or changed file.
  `rows_excluded_pharmacy`, `people_excluded_non_physician` — rows dropped on
  purpose (§1). `review_*` — entries sent to the review queue (new, changed,
  conflict, missing, unmatched). `safety_stop_would_trigger` — the apply
  would refuse to write (§2).
- Комора: `rows_parsed`, `parse_failures` (page references only, never the
  text), `attached`, `already_attached`, `locked`, `conflict`, `ambiguous`,
  `no_match`, `specialty_mismatch`, `expired`, `unmapped_specialties`,
  `missing_from_list`, `pruned_off_list`.

### Trying it on real data locally

Real downloads only into a scratch copy of the database, never the shared
preview or demo databases:

```sh
createdb -U martin -O zdravje -T zdravje_ui zdravje_w6x   # nothing connected to zdravje_ui
DB_DATABASE=zdravje_w6x php artisan migrate
DB_DATABASE=zdravje_w6x php artisan import:fzom --dry-run
dropdb -U martin zdravje_w6x
```

Delete the downloaded raw files afterwards and report counts only.

## 4. ФЗОМ „Шифрарник на лекари“ (`import:fzom`)

Facilities first, then doctors. One **facility** per institution = tax number
(ЕДБ) + town; ФЗОМ lists an institution once per contract unit, so all its ФЗО
codes are kept on the one facility. One **doctor** per ФЗО facsimile number,
linked to every facility they have a contract with; the primary-care contract
(or the newest) is the primary workplace.

Specialties go through **specialty aliases** (`specialty_aliases`, source
`fzom`): the first sighting of a wording stores the default from
`FzomSpecialtyCatalog`; after that the alias row decides, so a correction
there sticks. Imported specialties are created **hidden** and are published
together with the first doctor that uses them. See §7 for editing aliases.

The facsimile number is an internal matching key: not in the API, the diff
summary or the activity log.

Matching existing records: facility by ФЗО code, then by tax number (staff
-entered facilities only); doctor by facsimile, then by normalised name
(either word order) **and** a shared facility or the same specialty in the
same town. Several possible matches are never guessed: the row goes to the
review queue as *unmatched*.

### First dry run (2026-10-06 files, scratch copy of the UI database)

| | |
|---|---|
| rows read (both files) | 10,114 |
| pharmacy rows excluded | 2,021 |
| people excluded as non-physicians | 372 |
| doctors (distinct facsimiles) | 7,144 — 5,434 doctors + 1,710 dentists |
| doctors without any specialty | 193 (mostly laboratory staff; flagged on their review item) |
| facilities (institution × town) | 2,623 (298 hospitals, 104 laboratories, the rest clinics/practices) |
| unmapped specialty wordings | 0 |
| second run, same files | 0 created, 0 changed (7,144 + 2,623 unchanged) |

## 5. Лекарска комора licences (`import:komora-licences`)

The command reads the list page (`KOMORA_LIST_URL`), downloads the PDFs that
changed, parses every row (name, specialty, licence number, expiry) and
matches it to a profile:

- Candidates are profiles the **ФЗОМ import created** (`doctors.import_source
  = 'fzom'`, `KOMORA_MATCH_IMPORTED_SOURCE`), never dentists, never
  hand-made profiles: a licence is evidence about a doctor we know works
  somewhere. Website-only drafts are not matched automatically; check their
  licence by hand before publishing (their review item says so).
- The name must match exactly after normalisation (`NameKey`, either word
  order) **and** the licence's specialty must fit the doctor's (§7). One fit
  attaches; several fits are *ambiguous*; a name without a fitting specialty
  (or with wording nobody has mapped yet) is a *specialty mismatch*; no name
  is *no match*. Namesakes on the list never share a profile. Everything not
  attached goes to the review queue as *unmatched*.
- Attaching goes through the import core
  (`App\Support\Import\Contracts\DoctorLicenceSink` →
  `EloquentDoctorLicenceSink`): one licence number per doctor, never moved
  silently to another profile (that is a *conflict*), lockable as the field
  `licence`.
- Every licence of the list is also kept in the staging table
  `komora_licences` with the matching outcome (**Data import → Licences
  (Комора)**, filters for expired and off-the-list). A licence absent from a
  complete, cleanly parsed list is marked *missing since*; nothing is
  unpublished. Still absent from the next complete list and attached to no
  profile, its staging row is deleted (`pruned_off_list`); an attached one
  stays as the review signal.

The number and expiry date stay internal. A public profile shows only
`has_valid_licence` („Лиценца: важечка“) — a status checked against today's
date, which stays right between the four-monthly list updates, unlike a
printed expiry date. An expired licence or none shows nothing.

## 6. Institution websites (`import:institutions-json`)

Input: one research slice — `institutions.json`
(`{slice, generated_at, institutions: [{slug, name_mk, type, ownership,
address, town, phones, email, website, logo: {file, source_url}, covers:
[{file, source_url}], sources, workers: [{full_name, title, role,
specialty, department, source_url}]}]}`) with `logos/` and `covers/` next
to it. Keep these files outside the repository and outside public storage.
Provenance source `website`, with the page URL per field.

- **Facilities** match an existing one by website host, then by the same
  significant name words in the same town (legal form, quotes, the town and
  the municipality the register appends are ignored). A single facility
  whose name is contained in the website's longer name also matches, with a
  *check this match* review item. Otherwise a hidden draft. On a matched
  register facility the website only fills empty fields (phone, e-mail,
  website…) — it never replaces the register's name or address.
- **Doctors**: physicians and dentists only (nurses and other staff are
  skipped). Matched to a doctor already at that facility, or by name +
  specialty + town; otherwise a hidden draft whose review item says
  *verify licence before publishing*. Website specialties are free text;
  `SpecialtyText` reduces „Специјалист по општа хирургија“, „хирург-уролог“,
  „Офталмолог“ to catalogue wordings; the rest are stored as unmapped
  aliases (source `website`) and listed in the review queue.
- **Logo** → the facility avatar (status `trademark-identification`).
- **Cover photos** (owner decision 2026-10-07): the first candidate becomes
  the facility cover and is shown when the facility is published (status
  `website`); the other candidates are kept as alternatives.
- Every image keeps its source URL (`facility_media`). On the facility edit
  page, **Website images** offers **Use as cover** and **Remove image**:
  removal deletes the file at once, clears it from the profile, locks that
  field and is written to the activity log (`facility_media`); a removed
  image is never imported again. Use it for any takedown request.
- Photos of people are out of scope (not collected).

## 7. Specialty wording: aliases and the licence mapping

Two tables translate the sources' specialty wording. They answer different
questions and are edited separately:

| | Specialty aliases (`specialty_aliases`) | Licence specialty mapping (`licence_specialty_mappings`) |
|---|---|---|
| Question | Which of **our** specialties does a doctor with this ФЗОМ / website wording get? | Does a Комора licence's specialty **fit** a doctor's specialty? |
| Row | source (`fzom`, `website`) + wording → our specialty, or *excluded* (non-physician profession), or unmapped | source (`komora`, `fzom`) + wording → a group key (e.g. `kardiologija`), groups it is also compatible with, or *not a physician's specialty* |
| Used by | `import:fzom`, `import:institutions-json` (which specialty links a doctor gets; who is skipped) | `import:komora-licences` (whether a name match is accepted) |
| Edited in | **Data import → Specialty aliases** (`imports.manage`) | **Data import → Licence specialty mapping** (`licences.manage`) |

Mapping an alias (or marking it excluded) takes effect on the next import:
the doctors whose source record uses the wording are re-linked — the import
replaces only the specialty links it made itself, never staff-made ones. The
navigation badge counts unmapped wordings. Aliases are neither created nor
deleted by hand (a deleted one would come back with the catalogue default).

The two tables are kept apart on purpose: an alias picks exactly one of our
specialties, while the licence mapping groups wordings of two sources and
lets a licence fit several groups (a cardiologist contracted as an
internist). A doctor's groups come from their specialties: a mapping row linked to the
specialty, the specialty's name read as either source's wording (imported
specialties carry the ФЗОМ wording), or a slug that is itself a group key.

## 8. Reviewing and publishing (**Data import → Import review**)

| Kind | Meaning | Actions |
|---|---|---|
| New | a hidden draft the import created | Publish (one or bulk), Dismiss |
| Changed | an imported value replaced the old one on a **published** profile (one item per profile per run) | Mark seen |
| Conflict | the source disagrees with a value someone else set, or two records claim the same key (licence number); nothing was overwritten | Use imported value, Keep current and lock |
| Missing | absent from consecutive snapshots | Hide profile (reviews kept), Dismiss |
| Unmatched | ambiguous match, unmapped specialty wording, licence row without a single fitting doctor, partial name match | Dismiss after fixing by hand (an unmapped wording: map it in **Specialty aliases** or **Licence specialty mapping**) |

1. Check each draft against the source before publishing: imported data is
   not verified. **Publish selected drafts** (bulk) once a batch is checked;
   it also publishes the hidden imported specialties the doctor uses.
2. **Missing**: check whether the doctor still works there; hide only on
   evidence. The profile keeps its reviews.
3. The public profile may show „Лиценца: важечка“ (from the Комора list); the
   licence number, its expiry date and the ФЗО facsimile number are never
   public.

**Import runs** lists every run with its **Diff (CSV)** — one line per
create / update / conflict / locked / missing, with old and new values. It
holds names of people: download only to work on it, do not share.

Permissions: `imports.view` (runs, diffs, queue), `imports.manage` (act on the
queue, locks, specialty aliases), `licences.manage` (Licences (Комора),
Licence specialty mapping). Administrator only by default.

## 9. Undoing a bad run

An import never deletes, and new profiles stay hidden, so a bad run is
contained:

1. **Stop the schedule** for that source: set `IMPORT_FZOM_SCHEDULE=false`
   (or `IMPORT_KOMORA_SCHEDULE=false`) and reload the config.
2. **Drafts the run created**: leave them unpublished and dismiss their *new*
   items, or delete the profiles under **Directory**. They were never public.
3. **Values the run changed on published profiles**: the diff CSV of the run
   lists every field with old and new value; field provenance records which
   run set each value. Restore the old value on the profile's edit page and
   **lock** the field so the next run cannot set it again.
4. **Missing flags**: dismiss the run's *missing* entries in the review queue.
5. **A wrong licence**: correct it on the doctor and lock the field
   `licence`; the staging row in **Licences (Комора)** shows what matching
   decided.
6. If the damage is wide (thousands of fields), restore the database from the
   last backup taken before the run ([`docs/backup-restore.md`](backup-restore.md))
   instead of correcting by hand, then re-run with `--dry-run` once the cause
   (usually a changed source format) is fixed.

## 10. Schedule and alerts

`routes/console.php` registers both source imports. They are **off by
default**:

| Setting | Default | Meaning |
|---|---|---|
| `IMPORT_FZOM_SCHEDULE` | `false` | Run `import:fzom` weekly, Monday 05:30 |
| `IMPORT_KOMORA_SCHEDULE` | `false` | Run `import:komora-licences` monthly, on the 3rd at 06:00 |
| `IMPORT_ALERT_EMAIL` | unset → `PLATFORM_ALERT_EMAIL` | Who hears about failed or unusual runs |
| `IMPORT_LARGE_DIFF_RATIO` | `0.10` | Share of the records seen that a run created, changed or found missing before it counts as a large diff… |
| `IMPORT_LARGE_DIFF_MIN` | `25` | …and the minimum number of such profiles |
| `IMPORT_ALERT_THROTTLE_MINUTES` | `60` | One alert per source and kind per window |

(`config/data_ops.php`.) The scheduled commands run without options (a
real, conditional download). An enabled entry is still skipped, with a log
warning, while its command is not installed. Both run `onOneServer` and
`withoutOverlapping`.

Alerts (`App\Support\DataOps\ImportAlerter`, sent synchronously, counts and a
short error line only — never names or numbers):

- **Failed scheduled run**: the command exited non-zero (the scheduler's
  failure hook).
- **ImportRunFinished**: dispatched by `ImportRunAnnouncer` when a real
  (not dry, not *not modified*) `import:fzom` or `import:komora-licences` run
  ends, by hand or scheduled; `AlertOnImportRun` mails on a failed run or a
  large diff. Contract: `source` (`fzom` / `komora`), `succeeded`, `seen`
  (source records after filtering), `created`, `updated`, `missing`
  (profiles or licence links), `conflicts`, `unmatched` (review entries),
  `runId`, `error`, `reviewUrl` (the run in **Import runs**). Website imports
  are run by hand and not announced.

| Event field | ФЗОМ run counts | Комора run counts |
|---|---|---|
| `seen` | `doctors_in_source` + `facilities_in_source` | `rows_parsed` − `duplicate_numbers` |
| `created` | `doctors_created` + `dentists_created` + `facilities_created` | `attached` |
| `updated` | `review_changed` (published profiles changed) | 0 |
| `missing` | `review_missing` | `missing_from_list` |
| `conflicts` | `review_conflict` | `conflict` |
| `unmatched` | `review_unmatched` | `ambiguous` + `no_match` + `specialty_mismatch` + `doctor_not_found` |

A failed scheduled run can trigger both the failure hook and the event;
the alert throttle (per source and kind) sends one mail. The very first
apply of a source is a large diff by design: expect one alert.

## 11. Corrections and objections from the public

Every published doctor and clinical facility profile ends with a quiet
**„Пријави грешка во профилот“** link; doctor profiles also have
**„Барање за приговор / отстранување“**. Neither needs an account.

| | Correction | Objection / removal (doctors only) |
|---|---|---|
| Who | Anyone, the doctor included | The listed doctor |
| Form | Which part of the profile, what is wrong (≤ 1000 characters), optional e-mail for a reply | Who they are and what they ask (≤ 1000), required phone or e-mail for verification |
| Answer due | **15 days** from receipt (ЗЗЛП чл. 20) | **30 days** from receipt, with reasons (ЗЗЛП чл. 16(4), 21, 25) |
| API | `POST /api/v1/doctors/{slug}/corrections`, `POST /api/v1/facilities/{slug}/corrections` (`type`: `correction` / `objection`) | same, `type=objection` |

Abuse limits: 5 requests per 10 minutes and 15 per hour, per account or else
per address (the address is used for the limit only and not stored); a
hidden honeypot field drops bots silently. No captcha.

### Handling the queue

Admin panel → **Directory → Corrections** (`profile_corrections.view`;
closing needs `profile_corrections.resolve`; Administrator only by default).
Staff with access get one summary e-mail at most every 10 minutes when new
requests arrive (`corrections:alert-staff`; `CORRECTION_ALERT_EMAIL` adds a
shared inbox). The list is sorted by due date; overdue requests are red and
turn the navigation badge red.

1. Open the request; **Edit the profile** takes you to the doctor or facility
   edit page (needs `doctors.update` / `facilities.update`).
2. **Correction**: fix the profile (lock the field if an import would
   otherwise set it back), then **Mark corrected** with a note of what
   changed — or **No change** with the reason. If the person left an e-mail,
   reply to it; nothing is sent automatically.
3. **Objection / removal**:
   1. Verify the person by the contact they left (call the workplace, check
      the Комора list). An unverified objection is not decided on.
   2. Always remove when the person no longer practises or the profile is
      wrongly linked to them: **Uphold (profile removed)**. That unpublishes
      the doctor profile in the same step and records a **suppression**
      (below), so no import adds or publishes the person again. Delete the
      profile on its edit page as well if it should go entirely (the
      suppression outlives the delete).
   3. Otherwise do the balancing test (memo §2.1.1): the public interest in
      a complete, neutral directory against the person's reasons. If the
      profile stays, **Refuse (profile kept)** with the reasons as the note,
      and reply to the person with those reasons and their right to complain
      to АЗЛП or go to court.
4. Every close is written to the audit log (who, when, the decision; never
   the message, contact or note).

### Suppressed profiles (**Data import → Suppressed profiles**)

A doctor removed after an upheld objection, or deleted by staff (soft or
hard delete), is **suppressed**: `import_suppressions` keeps the keys the
sources could bring the person back by — the ФЗО facsimile, the licence
number, the source records that fed the profile, and the normalised name and
town — plus a label, the reason and the objection it came from.

- ФЗОМ skips a suppressed facsimile (and, for a profile staff made without
  one, the same name in the same town) — nothing is created, updated or
  stored, and the person never counts as missing (`doctors_suppressed`).
- The website import skips a suppressed source key, and a new profile with a
  suppressed name and town.
- The Комора import never attaches a licence to a suppressed doctor or a
  suppressed licence number, and queues no review item for it.
- **Publish** in the review queue refuses a suppressed doctor.
- Restoring a deleted doctor lifts its *deleted* suppression. An *objection*
  suppression stays until someone with `imports.manage` uses **Lift** —
  only when the person withdrew the objection in writing. The next import
  may then create the profile again, as a hidden draft.
5. **A logo or cover photo** an institution asks us to take down: **Website
   images → Remove image** on the facility edit page (§6).

Closed requests are deleted 365 days after they were closed
(`CORRECTION_RETENTION_DAYS`, `model:prune` daily at 04:50); open ones are
never pruned. When a member deletes their account, their requests stay for
staff but lose the account link and the contact.
