#!/usr/bin/env bash
#
# Print the live v1 endpoint table for docs/api-contract.md:
#
#   ./scripts/api-routes.sh
#
# Regenerate and paste into the contract whenever routes change, rather than
# editing that table by hand — it is how the document drifted in the first place.
#
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"

(cd "${root}/apps/api" && php artisan route:list --path=api/v1 --json) \
  | python3 "${root}/scripts/api-routes.py"
