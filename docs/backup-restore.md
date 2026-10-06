# Backup, restore and rollback

How to take a backup, restore it, and decide between rolling back code,
rolling back migrations and restoring. No host is chosen yet (local only), so
the managed-database parts are marked **when hosted**; everything else was
rehearsed locally and the timings below are real.

Related: [infra/deploy.md](../infra/deploy.md) "Backups" and "Pre-deploy data
checks", [migration-policy.md](./migration-policy.md).

## 1. Taking a backup

```bash
DB_HOST=… DB_PORT=5432 DB_DATABASE=zdravje360 DB_USERNAME=… DB_PASSWORD=… \
  ./scripts/db-backup.sh /secure/backups
```

[`scripts/db-backup.sh`](../scripts/db-backup.sh) runs `pg_dump` in the custom
format (compressed, restorable in parallel and table by table), owner-only file
permissions, then reads the archive's table of contents back so a truncated
dump fails loudly instead of sitting there looking like a backup. It prints the
path, size, entry count and SHA-256. It reads the connection only from the
environment, never from `apps/api/.env`.

- **Before every `migrate --force`**, take one and keep it until the release is
  known good.
- **When hosted:** turn on the provider's automated daily backups with
  point-in-time recovery as well. A dump is a snapshot at one instant; PITR is
  what recovers the last hour.
- Dumps hold personal data (accounts, reviews). Store them encrypted, outside
  the repository (`backups/` and `*.dump` are git-ignored), and delete them on
  the same schedule as the data they hold (`docs/data-inventory.md`).
- Media files (avatars, covers) are not in the database. With `MEDIA_DISK=s3`
  rely on bucket versioning; with `public`, copy `storage/app/public` alongside.

## 2. Restoring

Into an **empty** database owned by the application user:

```bash
createdb -O zdravje zdravje360_restore          # as a role that can create databases
PGPASSWORD=… pg_restore \
  --host=… --username=zdravje --dbname=zdravje360_restore \
  --no-owner --no-acl --no-comments --exit-on-error --jobs=4 \
  /secure/backups/zdravje360-20261006T141830Z.dump
```

- `--no-comments` is required when the application user is not a superuser
  (every managed database): the dump carries `COMMENT ON EXTENSION pg_trgm`,
  which only the extension's owner may run, and `--exit-on-error` would stop
  there. Found in the drill below.
- `pg_trgm` is a trusted extension (PostgreSQL 13+), so the database owner
  recreates it during the restore without superuser rights.
- `--jobs` parallelises data load and index builds; use the server's core count.

Then point the API at it and check before sending traffic:

```bash
php artisan migrate:status        # no "Pending" rows, unless the restore predates a release
php artisan platform:preflight
curl -fsS "$API_URL/api/v1/health"
```

Restoring over the live database: stop the queue workers and scheduler, put the
site in maintenance (Filament **Site settings**), restore into a new database,
verify it as above, then switch `DB_DATABASE` (and `config:cache`) rather than
dropping the live one first. Keep the old one until the restored one has served
for a day.

## 3. Local drill (6 October 2026)

Rehearsed on a scratch database, never on `zdravje_ui` or `zdravje_e2e`:
`zdravje_wp4_drill` migrated (54 migrations) and filled with `PerfSeeder`, then
restored into a fresh `zdravje_wp4_drill_restore`. Apple M2 (8 cores),
PostgreSQL 16.15 on the same machine, so network time is excluded.

| Step | Result |
|---|---|
| Source | 59 MB on disk: 2,500 users, 5,000 doctors, 50,000 reviews, 20,000 forum posts; 124 indexes |
| `db-backup.sh` (pg_dump, custom, compress 6) | 0.41 s, 2.6 MB archive, 378 entries |
| `pg_restore --jobs=4` | 0.42 s |
| `pg_restore` (single job) | 1.27 s |
| Restored database | 56 MB; identical row counts, 54 migrations recorded, 124 indexes, `reviews_id_seq` at 50,000, `pg_trgm` 1.6 present |
| `migrate:status` on the restore | 0 pending |
| `migrate:rollback --step=1` then `migrate` (the `users.role` drop, on the restore) | 0.42 s / 0.25 s; the rollback refilled `role` for all 2,500 accounts |

Here the archive was about 4–5 % of the on-disk size. Expect both directions to
grow roughly with data size, plus network time to a managed host; these numbers
say nothing about a production-sized database. **Re-time it on the real host
before launch** (on the plan as the restore rehearsal, blocked on hosting).

## 4. Rollback: which tool for which failure

Decide in this order; each step loses more than the one before.

1. **Roll back the code only** (redeploy the previous release). The default,
   and always possible, because migrations follow the expand/contract rule
   ([migration-policy.md](./migration-policy.md) §1): the previous release runs
   on the new schema. Workers and the scheduler restart with it.
2. **Roll back migrations** (`php artisan migrate:rollback --step=N --force`,
   where N is the number this release added — `migrate:status` shows them by
   batch, so `migrate:rollback` without `--step` undoes exactly the last
   deploy's batch). Only when the new schema itself is the problem, and only for
   migrations whose `down()` is real; an empty `down()` with a docblock means
   that migration cannot be undone this way (the email normalisation and the
   contested-registration flag are two). Roll back the code first if the new
   code needs the new schema.
3. **Restore the pre-deploy dump** (section 2). Only for data damage a `down()`
   cannot repair. Everything written since the dump is lost — reviews, forum
   posts, sign-ups — so first export what changed since (by `created_at` /
   `updated_at`) if it can be re-applied, and tell the owner what will be lost.

After any of them: `platform:preflight`, `/api/v1/health`, a sign-in, and
`php artisan queue:failed` (jobs that failed during the incident can be retried).
