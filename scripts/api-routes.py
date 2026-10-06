#!/usr/bin/env python3
"""Render the live v1 endpoint table for docs/api-contract.md.

Reads `php artisan route:list --path=api/v1 --json` on stdin.

The hand-maintained table in that document had drifted to roughly 50
inaccuracies — whole endpoints missing, and a stated premise ("no public
registration") that had been false for months. Regenerate rather than edit.

The output must match `php artisan docs:route-table` byte for byte —
RouteTableIsCurrentTest compares the committed table against that command —
so middleware is printed under the alias the route file uses, not the class
`route:list` resolves it to.
"""

import json
import sys

# Resolved class basename -> the alias routes/api.php registers it under.
ALIAS = {
    "EnsureModuleEnabled": "module",
    "EnsureUserRole": "role",
    "EnsureRegistrationsEnabled": "registrations",
    "EnsureEmailIsVerified": "verified",
    "OptionalSanctumAuth": "auth.sanctum.optional",
    "SetPublicCacheHeaders": "cache.public",
    "ValidateSignature": "signed",
    "Authenticate": "auth",
    "ThrottleRequests": "throttle",
}

# Applied to the whole v1 group; listing them on every row is noise. Only the
# group-wide limiter is hidden — named limiters such as throttle:api-login are
# the per-route guard a client most needs to know about, so they stay.
IMPLICIT = {"api", "throttle:api"}

SKIPPED_METHODS = {"HEAD", "OPTIONS"}


def short_name(middleware: str) -> str:
    name, _, argument = middleware.partition(":")
    base = name.split("\\")[-1]
    label = ALIAS.get(base, base)
    return f"{label}:{argument}" if argument else label


def main() -> None:
    routes = json.load(sys.stdin)
    rows = {}

    for route in routes:
        path = "/" + route["uri"].removeprefix("api/v1").lstrip("/")

        guards = []
        for middleware in route.get("middleware", []):
            label = short_name(middleware)
            if label in IMPLICIT:
                continue
            guards.append(f"`{label}`")

        for method in route["method"].split("|"):
            if method in SKIPPED_METHODS:
                continue
            rows[f"{method} {path}"] = (
                f"| `{method}` | `{path}` | {', '.join(guards) or '—'} |"
            )

    print("| Method | Path | Guards |")
    print("|--------|------|--------|")
    for key in sorted(rows):
        print(rows[key])


if __name__ == "__main__":
    main()
