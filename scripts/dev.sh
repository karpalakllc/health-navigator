#!/usr/bin/env bash
# Start local API (:8000) and web (:3000). Run from repo root: ./scripts/dev.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
API_DIR="$ROOT/apps/api"
WEB_DIR="$ROOT/apps/web"

port_listen() {
  lsof -nP -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1
}

if port_listen 8000; then
  echo "API already listening on http://127.0.0.1:8000"
else
  echo "Starting API on http://127.0.0.1:8000 ..."
  (cd "$API_DIR" && php artisan serve --host=127.0.0.1 --port=8000) &
fi

if port_listen 3000; then
  echo "Web already listening on http://localhost:3000"
else
  echo "Starting web on http://localhost:3000 ..."
  (cd "$WEB_DIR" && npm run dev) &
fi

echo "Waiting for servers (Ctrl+C stops this script; servers keep running in background)."
wait
