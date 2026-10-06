# Directory data: imports, corrections and objections (runbook)

How doctor and facility data gets into the directory from public sources, how
staff review and publish it, how to undo a bad run, and how the public asks for
a correction or a removal. Background and legal reasoning:
[`docs/legal/research-memo.md`](legal/research-memo.md) §2.1 and §2.1.1.
Personal data held: [`docs/data-inventory.md`](data-inventory.md).

Owners: W6-A (import core, `import:fzom`), W6-B (`import:komora-licences`),
W6-C (schedule, alerts, corrections, this runbook). Where this page names an
option or screen of the import commands, check it against
`php artisan <command> --help` after the three branches are merged; the
command code is authoritative.

---

## 1. Sources

| Source | Command | What we take | What we never take | Cadence |
|---|---|---|---|---|
| ФЗОМ „Шифрарник на лекари“ (two XML files: primary care, specialist/hospital) | `import:fzom` | Doctor first and last name, specialty, institution (name, ФЗО code, tax number), work unit, address, town; the facsimile number as an **internal** matching key | Pharmacists (`TipDogovor` „Аптеки“), nurses' names (`ClenNaTim`), absence reasons (`PricinaOtsustvo` / `StatusValidnostID`), who substitutes for whom (`RedovnaZamena`), any phone or e-mail of a person | Weekly (Monday 05:30), once enabled |
| Лекарска комора „Листа на доктори со важечки лиценци“ (five PDFs) | `import:komora-licences` | Name, licensed specialty, licence expiry, licence number (**internal** only: matching and dedup) | Anything not in the list; the number is never shown or exported publicly | Monthly (3rd, 06:00), once enabled; the Комора republishes about every four months |

Dentists come from ФЗОМ only (there is no public dental licence list). Both
importers identify themselves with a User-Agent and contact address
(`IMPORT_USER_AGENT`, `IMPORT_CONTACT` — set a monitored address before the
first real run), respect `robots.txt`, pause between requests and send
conditional requests (`If-Modified-Since` / `ETag`). Raw downloads are kept on
the **private** import disk (`IMPORT_DISK`, never the public media disk), the
newest few per source only (`IMPORT_SNAPSHOT_RETENTION`), and never in git.

## 2. Running an import by hand

Always start with a dry run. It executes exactly the same code inside a
transaction that is rolled back, so its counts and diff are what an apply
would do.

```sh
php artisan import:fzom --dry-run
php artisan import:fzom              # apply
php artisan import:komora-licences --dry-run
php artisan import:komora-licences   # apply
```

`import:fzom` also takes `--pzz=<file>` / `--spec=<file>` to use local XML
files instead of downloading, and `--force` to import although the source
answered 304 Not Modified.

Each run writes an `import_runs` row (status, counts, error) and a diff
summary CSV on the private import disk; both are in the admin panel under
**Data import** (permission `imports.view`).

Read the counts before applying:

- `rows_read`, `doctors_in_source`, `facilities_in_source` — roughly stable
  week to week. A sudden drop means a truncated or changed file.
- `rows_excluded_pharmacy`, `people_excluded_non_physician` — rows we drop on
  purpose (§1).
- `review_*` — entries the run sent to the review queue (new, changed,
  conflict, missing, unmatched).
- `safety_stop_would_trigger` — the apply would mark more than
  `IMPORT_MAX_MISSING_RATIO` (default 20 %) of known records missing; an
  apply refuses to write in that case.

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

## 3. Reviewing and publishing

Imported profiles are **hidden drafts** until staff publish them. Nothing is
deleted or unpublished by an import.

1. **Data import → Review queue** (`imports.manage` to act):
   - **New** — a draft profile from the source. Check name, specialty and
     workplace against the source row; publish when right.
   - **Changed** — the source changed a value on a profile. Accept or keep
     ours. Fields an editor **locked** on the doctor or facility edit page are
     never overwritten by an import.
   - **Conflict** — the source disagrees with a staff-entered or locked
     value, or two records claim the same key (facsimile, licence number).
   - **Missing** — not in the source for two consecutive complete runs
     (`IMPORT_MISSING_AFTER_RUNS`). Check whether the doctor still works
     there; unpublish only on evidence. The profile keeps its reviews.
   - **Unmatched** — a licence row with no doctor, or with more than one
     candidate (duplicate names are never matched automatically).
2. **Bulk publish** from the queue once a batch is checked.
3. The public profile may show „Лиценца: важечка“ (from the Комора list); the
   licence number, its expiry date and the ФЗО facsimile number are never
   public.

## 4. Undoing a bad run

An import never deletes, and new profiles stay hidden, so a bad run is
contained:

1. **Stop the schedule** for that source: set `IMPORT_FZOM_SCHEDULE=false`
   (or `IMPORT_KOMORA_SCHEDULE=false`) and reload the config.
2. **Drafts the run created**: leave them unpublished, or delete them from the
   review queue / directory. They were never public.
3. **Values the run changed on published profiles**: the diff CSV of the run
   lists every field with old and new value; field provenance records which
   run set each value. Restore the old value on the profile's edit page and
   **lock** the field so the next run cannot set it again.
4. **Missing flags**: dismiss the run's „missing“ entries in the review queue.
5. If the damage is wide (thousands of fields), restore the database from the
   last backup taken before the run ([`docs/backup-restore.md`](backup-restore.md))
   instead of correcting by hand, then re-run with `--dry-run` once the cause
   (usually a changed source format) is fixed.

## 5. Schedule and alerts

`routes/console.php` registers both imports. They are **off by default**:

| Setting | Default | Meaning |
|---|---|---|
| `IMPORT_FZOM_SCHEDULE` | `false` | Run `import:fzom` weekly, Monday 05:30 |
| `IMPORT_KOMORA_SCHEDULE` | `false` | Run `import:komora-licences` monthly, on the 3rd at 06:00 |
| `IMPORT_ALERT_EMAIL` | unset → `PLATFORM_ALERT_EMAIL` | Who hears about failed or unusual runs |
| `IMPORT_LARGE_DIFF_RATIO` | `0.10` | Share of the records seen that a run created, changed or found missing before it counts as a large diff… |
| `IMPORT_LARGE_DIFF_MIN` | `25` | …and the minimum number of such profiles |
| `IMPORT_ALERT_THROTTLE_MINUTES` | `60` | One alert per source and kind per window |

(`config/data_ops.php`.) An enabled entry is still skipped, with a log
warning, while its command is not installed. Both run `onOneServer` and
`withoutOverlapping`.

Alerts (`App\Support\DataOps\ImportAlerter`, sent synchronously, counts and a
short error line only — never names or numbers):

- **Failed scheduled run**: the command exited non-zero (the scheduler's
  failure hook).
- **ImportRunFinished**: the import code dispatches
  `App\Events\ImportRunFinished` when a run ends; `AlertOnImportRun` mails on
  a failed run or a large diff. Contract: `source` (`fzom` / `komora`),
  `succeeded`, `seen` (source records after filtering), `created`, `updated`,
  `missing` (profiles or licence links), `conflicts`, `unmatched` (review
  entries), optional `runId`, `error`, `reviewUrl`.

**Integration note (open):** at the time of writing the import core does not
dispatch `ImportRunFinished` yet. Wire it once, where a run finishes or
fails (`ImportRunner::run`), mapping the run's counts — e.g. `seen` =
`doctors_in_source`, `missing` = `review_missing`, `conflicts` =
`review_conflict`, `unmatched` = `review_unmatched`, `created` / `updated`
from the run's created and changed counts. Skip dry runs and `not_modified`
runs. Until then only the scheduler's failure hook alerts.

## 6. Corrections and objections from the public

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
      wrongly linked to them (unpublish or delete on the edit page), then
      **Uphold (profile removed)**.
   3. Otherwise do the balancing test (memo §2.1.1): the public interest in
      a complete, neutral directory against the person's reasons. If the
      profile stays, **Refuse (profile kept)** with the reasons as the note,
      and reply to the person with those reasons and their right to complain
      to АЗЛП or go to court.
4. Every close is written to the audit log (who, when, the decision; never
   the message, contact or note).

Closed requests are deleted 365 days after they were closed
(`CORRECTION_RETENTION_DAYS`, `model:prune` daily at 04:50); open ones are
never pruned. When a member deletes their account, their requests stay for
staff but lose the account link and the contact.
