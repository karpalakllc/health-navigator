#!/usr/bin/env bash
#
# Print the live v1 endpoint table for docs/api-contract.md:
#
#   ./scripts/api-routes.sh
#
# A thin wrapper over `php artisan docs:route-table` (add --write there to
# rewrite the contract in place). It used to re-render `route:list --json` in
# Python, which drifted from the command RouteTableIsCurrentTest checks:
# route:list resolves `can:` to `Authorize` and reorders middleware.
#
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"

cd "${root}/apps/api" && php artisan docs:route-table
