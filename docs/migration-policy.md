# Migration policy

Rules for every migration in `apps/api/database/migrations`. They exist so a
deploy can always go back: either by rolling back the code alone (the default),
or by rolling back migrations, or, as the last resort, by restoring the
pre-deploy dump ([backup-restore.md](./backup-restore.md)).

Reviewers check these in [pr-verification.md](./pr-verification.md)
("Migrations, both directions").

## 1. The previous release must run on the new schema

Code is rolled back far more often than schemas. So a migration may only make
changes the **currently deployed** code tolerates:

- **Adding** a nullable column, a column with a default, a table or an index: fine.
- **Removing or renaming** a column or table: two releases (expand, then
  contract). Release N stops reading and writing it (and makes it nullable if it
  was `NOT NULL`); release N+1 drops it. `users.role` is the worked example:
  `2026_10_10_100001_deprecate_users_role_column` (nullable, unread) shipped
  first, `2026_10_13_100000_drop_role_from_users_table` dropped it a release
  later.
- **Changing a type or tightening a constraint** (`NOT NULL`, unique, a shorter
  length): backfill first, in its own migration, then constrain; check existing
  rows with a pre-deploy query in `infra/deploy.md` "Pre-deploy data checks".

## 2. `down()` works, or says why it cannot

- `down()` must restore the previous schema **and** the data the old code needs
  (the `users.role` drop refills the column from Spatie roles).
- Where that is impossible (a lossy normalisation, a one-way flag), leave
  `down()` empty with a docblock saying so, and add the matching pre-deploy
  check to `infra/deploy.md`. An irreversible migration is fine; a `down()` that
  throws or silently loses data is not.
- Both directions are run on PostgreSQL before merge:
  `migrate:fresh`, `migrate:rollback --step=N`, `migrate`.

## 3. Both databases

Tests run on SQLite, production on PostgreSQL 16, and the CI runs both. Guard
driver-specific DDL (`pg_trgm` indexes, partial indexes, `ALTER TYPE`) with
`DB::getDriverName()` and keep a test that executes the Postgres branch (the
`api-postgres` job). Never use raw SQL whose meaning differs by engine
(`LOWER()` folding, `NULL` ordering) for data changes; do them in PHP through
the same helpers the application uses (see
`2026_10_06_100000_normalise_user_emails`).

## 4. Data migrations

- **Idempotent**: running `up()` twice changes nothing the second time
  (`whereNull(...)`, `insertOrIgnore`, existence checks).
- **Chunked**: `chunkById(500)` or `cursor()`, never `->get()` on a table that
  grows with users (reviews, forum posts, analytics events).
- **Refuse rather than guess**: when the data needs a human decision
  (colliding accounts), throw with the ids and let the deploy stop before
  anything changes.
- No model classes whose code may change later: use `DB::table()`, or freeze
  the logic inside the migration.

## 5. Locks and long runs

`migrate --force` runs while the previous release still serves traffic.

- On PostgreSQL, adding a column with a constant default and adding a nullable
  column are instant; rewriting a column type or adding `NOT NULL` without a
  default rewrites or scans the table under an exclusive lock.
- An index on a large table blocks writes while it builds. Create it in its own
  migration so a slow build is easy to see and to rerun.
- Time any migration that touches a large table on a volume copy
  (`PerfSeeder` gives 5k doctors / 50k reviews) and write the number in the PR.

## 6. Never edit a shipped migration

Once a migration has run anywhere outside a developer's machine, fix it with a
new migration. Editing it leaves deployed databases in a state nothing
describes.

## 7. Naming and order

`YYYY_MM_DD_HHMMSS_verb_object.php`, dated after the newest existing file, so
ordering matches the order the changes were made. Parallel branches pick
distinct timestamps; when two branches touch the same table, the later one
rebases and re-runs both directions.

## 8. Every deploy

1. `scripts/db-backup.sh` (keep the dump until the release is known good).
2. Pre-deploy data checks for the migrations in this release (`infra/deploy.md`).
3. `php artisan migrate --force`.
4. If it fails part-way: PostgreSQL runs each migration in a transaction, so the
   failing one is rolled back; fix forward or roll back the ones that ran
   (`migrate:rollback --step=N`), per [backup-restore.md](./backup-restore.md).
