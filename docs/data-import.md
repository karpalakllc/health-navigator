# Directory data import

How doctors and facilities get into the directory from public sources, and
how staff review and publish them. Code: `apps/api/app/Support/Import/**`,
commands `import:fzom` and `import:institutions-json`, admin group
**Data import** (`/admin`). Sources and the legal reasoning:
`docs/legal/research-memo.md`; personal data held: `docs/data-inventory.md`.

> W6-C extends this file with the scheduler, alerts, the correction and
> objection flows and the full rollback procedure. The sections below are
> the import core (W6-A).

## 1. Guarantees every import keeps

- **Nothing goes public by itself.** New doctors and facilities are hidden
  drafts (`is_published = false`). Staff publish them from the review queue.
  An import never publishes, unpublishes or deletes a profile.
- **Dry run first.** `--dry-run` runs exactly the same code inside one
  database transaction and rolls it back. The run record, its counts and
  its diff summary are kept, so you see what an apply would do. A dry run
  stores no image files.
- **Idempotent.** Each source record is stored with a hash
  (`source_records`); a record that did not change is skipped. Running the
  same files twice changes nothing the second time.
- **Batches.** An apply commits every batch (`IMPORT_BATCH_SIZE`, default
  250) in its own transaction. A failed run keeps the finished batches and
  can simply be run again.
- **Provenance per field** (`field_provenance`): which source wrote the
  value, the value it wrote, when, the source record and, for web sources,
  the page URL.
- **Staff edits win.** If a field's current value is not what the import
  last wrote, somebody changed it: the import does not overwrite it and
  raises a **conflict** instead. A link (specialty, workplace) that staff
  removed is not added back.
- **Locks.** On a doctor or facility edit page, **Import locks** lists the
  fields with their source; a locked field is never written by any import.
- **Missing ≠ deleted.** A record absent from two consecutive complete
  ФЗОМ snapshots (`IMPORT_MISSING_AFTER_RUNS`) is queued as **missing**.
  Staff decide (hide or keep).
- **Safety stop.** An apply that would mark more than 20 %
  (`IMPORT_MAX_MISSING_RATIO`) of the known doctors missing aborts before
  writing — that is what a truncated source file looks like.
- **No search or audit-log noise.** Imports do not touch the search index
  (drafts are not searchable anyway) or write one activity-log entry per
  record; the run, its diff and the provenance are the record. Publishing
  goes through the normal model events (search index, activity log).

## 2. ФЗОМ „Шифрарник на лекари“ (`import:fzom`)

```bash
php artisan import:fzom --dry-run      # download, report, write nothing
php artisan import:fzom                # apply
php artisan import:fzom --pzz=/path/a.xml --spec=/path/b.xml --dry-run   # local files
php artisan import:fzom --force        # import even if the source answers 304
```

Fetching: robots.txt is read first and honoured; requests identify
themselves (`IMPORT_USER_AGENT` + `IMPORT_CONTACT` — **set a monitored
address before the first real run**); requests are spaced
(`IMPORT_REQUEST_DELAY`, 5 s); conditional GET (ETag / Last-Modified) — if
both files are unchanged since the last apply the run ends as *not
modified*. Raw files are stored on the **private** import disk
(`storage/app/private/imports/snapshots/fzom/…`, never public storage or
git); only the newest 3 snapshots are kept (`IMPORT_SNAPSHOT_RETENTION`).

What is imported: facilities first, then doctors. One **facility** per
institution = tax number (ЕДБ) + town; ФЗОМ lists an institution once per
contract unit, so all its ФЗО codes are kept on the one facility. One
**doctor** per ФЗО facsimile number, linked to every facility they have a
contract with; the primary-care contract (or the newest) is the primary
workplace. Specialties go through `specialty_aliases` (source `fzom`): the
first sighting of a wording stores the default from
`FzomSpecialtyCatalog`; after that the alias row decides, so a correction
there sticks. Imported specialties are created **hidden** and are published
together with the first doctor that uses them.

Never imported (dropped by the XML reader, never stored): pharmacy
contracts (`TipDogovorID` 4), the team nurse (`ClenNaTim`), absence reasons
and validity statuses (`PricinaOtsustvo`, `*StatusValidnostID`), every
substitution (`RedovnaZamena`, `VZamena*`), and people whose every specialty
is a non-physician profession (pharmacist, psychologist, speech therapist,
engineer, technologist…). The facsimile number is an internal matching key:
not in the API, the diff summary or the activity log.

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

## 3. Institution websites (`import:institutions-json`)

```bash
php artisan import:institutions-json /path/<slice>/institutions.json --dry-run
php artisan import:institutions-json /path/<slice>/institutions.json
```

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

## 4. Review queue (**Data import → Import review**)

| Kind | Meaning | Actions |
|---|---|---|
| New | a hidden draft the import created | Publish (one or bulk), Dismiss |
| Changed | an imported value replaced the old one on a **published** profile (one item per profile per run) | Mark seen |
| Conflict | the source disagrees with a value someone else set; nothing was overwritten | Use imported value, Keep current and lock |
| Missing | absent from consecutive snapshots | Hide profile (reviews kept), Dismiss |
| Unmatched | ambiguous match, unmapped specialty wording, licence row without a doctor, partial name match | Dismiss after fixing by hand |

Check each draft before publishing: imported data is not verified. Bulk
publish also publishes the hidden imported specialties the doctor uses.

**Import runs** lists every run (source, dry run or apply, outcome, counts)
with its **Diff (CSV)** — one line per create / update / conflict / locked /
missing, with old and new values. It holds names of people: download only
to work on it, do not share.

Permissions: `imports.view` (runs, diffs, queue), `imports.manage` (act on
the queue, locks). Administrator only by default.

## 5. Licences (Лекарска комора)

The licence matcher (W6-B) hands its results to
`App\Support\Import\Contracts\DoctorLicenceSink`
(`EloquentDoctorLicenceSink`): one licence number per doctor, never moved
silently, lockable as the field `licence`, conflicts and ambiguous rows to
the review queue. The number and expiry date stay internal; a public
profile shows only `has_valid_licence` („Лиценца: важечка“) — a status
checked against today's date, which stays right between the four-monthly
list updates, unlike a printed expiry date.
