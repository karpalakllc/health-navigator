#!/usr/bin/env bash
# Browser E2E suite against the real stack. Run from anywhere: ./scripts/e2e.sh
#
# Playwright (apps/web/playwright.config.ts) builds the web app, starts the API
# on :8010 and `next start` on :3010, resets and seeds the zdravje_e2e database
# (E2ESeeder) and runs the specs in apps/web/e2e. Extra arguments go straight to
# `playwright test`, e.g. ./scripts/e2e.sh forum --headed
#
# Prerequisites: PHP + composer install in apps/api, Node 24 + npm ci in
# apps/web, and a PostgreSQL database zdravje_e2e (user zdravje / secret on
# 127.0.0.1:5432 by default; override with E2E_DB_* variables).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
API_DIR="$ROOT/apps/api"
WEB_DIR="$ROOT/apps/web"

fail() {
  echo "e2e: $*" >&2
  exit 1
}

command -v php >/dev/null || fail "php not found"
[ -d "$API_DIR/vendor" ] || fail "apps/api/vendor missing — run composer install in apps/api"
[ -d "$WEB_DIR/node_modules" ] || fail "apps/web/node_modules missing — run npm ci in apps/web"

node_major="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"
[ "$node_major" -ge 24 ] || fail "Node 24+ required (found $(node -v 2>/dev/null || echo none))"

# Another process on an E2E port would be tested in place of this checkout.
# E2E_REUSE=1 opts in to attaching to servers you started yourself (never in CI).
for port in "${E2E_API_PORT:-8010}" "${E2E_WEB_PORT:-3010}"; do
  if lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1; then
    if [ "${E2E_REUSE:-}" = "1" ] && [ -z "${CI:-}" ]; then
      echo "e2e: port $port is already in use; reusing that server (E2E_REUSE=1)." >&2
    else
      fail "port $port is already in use — stop that process, or set E2E_REUSE=1 to test against it"
    fi
  fi
done

# The run starts with migrate:fresh, which drops every table: only ever on a
# local database whose name says it is disposable (e2e/global-setup.ts checks
# the same).
db_name="${E2E_DB_DATABASE:-zdravje_e2e}"
db_host="${E2E_DB_HOST:-127.0.0.1}"
[[ "$db_name" == *_e2e ]] || fail "refusing to reset database \"$db_name\" — its name must end in _e2e"
case "$db_host" in
  127.0.0.1 | localhost | ::1) ;;
  *) fail "refusing to reset a database on \"$db_host\" — only 127.0.0.1, localhost or ::1" ;;
esac

# Fails fast with a readable message instead of a migrate:fresh stack trace.
DB_CONNECTION=pgsql \
  DB_HOST="$db_host" DB_PORT="${E2E_DB_PORT:-5432}" \
  DB_DATABASE="$db_name" \
  DB_USERNAME="${E2E_DB_USERNAME:-zdravje}" DB_PASSWORD="${E2E_DB_PASSWORD:-secret}" \
  php -r '
    try {
      new PDO(
        sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE")),
        getenv("DB_USERNAME"), getenv("DB_PASSWORD"),
      );
    } catch (Throwable $e) {
      fwrite(STDERR, "e2e: cannot reach the E2E database: ".$e->getMessage().PHP_EOL);
      exit(1);
    }
  ' || exit 1

cd "$WEB_DIR"
npx playwright install chromium >/dev/null
exec npx playwright test "$@"
