#!/usr/bin/env bash
# Logical backup of the API's PostgreSQL database. Run from anywhere:
#
#   DB_HOST=… DB_PORT=5432 DB_DATABASE=zdravje360 DB_USERNAME=… DB_PASSWORD=… \
#     ./scripts/db-backup.sh [output-dir]
#
# Writes <output-dir>/<database>-<UTC timestamp>.dump in pg_dump's custom format
# (compressed, restorable table by table with pg_restore, and in parallel with
# -j), readable by its owner only, then reads the archive's table of contents
# back to prove it is complete. Prints the path, size and SHA-256.
#
# The connection comes from the same DB_* variables the API uses, and only from
# the environment: the script never reads apps/api/.env, so it cannot back up a
# different database than the one you named. Default output dir: ./backups.
#
# Restore and the rollback runbook: docs/backup-restore.md.
set -euo pipefail

fail() {
  echo "db-backup: $*" >&2
  exit 1
}

command -v pg_dump >/dev/null || fail "pg_dump not found (PostgreSQL client tools)"
command -v pg_restore >/dev/null || fail "pg_restore not found (PostgreSQL client tools)"

: "${DB_DATABASE:?set DB_DATABASE (and DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD)}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-$(id -un)}"

out_dir="${1:-backups}"
mkdir -p "$out_dir"
umask 077

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
file="$out_dir/${DB_DATABASE}-${stamp}.dump"
partial="$file.partial"

# A failed or interrupted dump must not leave a file that looks like a backup.
trap 'rm -f "$partial"' EXIT

started=$(date +%s)
# --no-password: never wait at a password prompt (cron, CI); a missing or
# wrong DB_PASSWORD fails at once instead.
PGPASSWORD="${DB_PASSWORD:-}" pg_dump \
  --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" --no-password \
  --dbname="$DB_DATABASE" \
  --format=custom --compress=6 \
  --no-owner --no-acl \
  --file="$partial"

# Reads every entry of the archive's table of contents; a truncated file fails.
entries="$(pg_restore --list "$partial" | grep -cv '^;')" || fail "archive unreadable: $partial"
[ "$entries" -gt 0 ] || fail "archive has no entries: $partial"

mv "$partial" "$file"
trap - EXIT

elapsed=$(( $(date +%s) - started ))
size="$(du -h "$file" | cut -f1)"
if command -v sha256sum >/dev/null; then
  sum="$(sha256sum "$file" | cut -d' ' -f1)"
else
  sum="$(shasum -a 256 "$file" | cut -d' ' -f1)"
fi

echo "db-backup: $file"
echo "db-backup: ${size}, ${entries} archive entries, ${elapsed}s, sha256 ${sum}"
