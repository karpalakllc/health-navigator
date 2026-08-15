#!/usr/bin/env python3
"""Render the live v1 endpoint table for docs/api-contract.md.

Reads `php artisan route:list --path=api/v1 --json` on stdin.

The hand-maintained table in that document had drifted to roughly 50
inaccuracies — whole endpoints missing, and a stated premise ("no public
registration") that had been false for months. Regenerate rather than edit.
"""

import json
import sys

SHORT = {
    "EnsureModuleEnabled": "module",
    "EnsureUserRole": "role",
    "EnsureRegistrationsEnabled": "registrations",
    "EnsureNotInMaintenance": "maintenance",
    "OptionalSanctumAuth": "optional-auth",
    "SetApiLocale": "locale",
    "Authenticate": "auth",
    "ThrottleRequests": "throttle",
}

# Applied to the whole group; listing it on every row is noise.
IMPLICIT = {"api", "maintenance", "locale", "throttle"}


def short_name(middleware: str) -> str:
    name, _, argument = middleware.partition(":")
    base = name.split("\\")[-1]
    label = SHORT.get(base, base)
    return f"{label}:{argument}" if argument else label


def main() -> None:
    rows = json.load(sys.stdin)

    print("| Method | Path | Guards |")
    print("|--------|------|--------|")

    for route in sorted(rows, key=lambda r: r["uri"]):
        method = route["method"].replace("|HEAD", "")
        path = "/" + route["uri"].removeprefix("api/v1").lstrip("/")

        guards = []
        for middleware in route.get("middleware", []):
            label = short_name(middleware)
            if label.split(":")[0] in IMPLICIT:
                continue
            guards.append(f"`{label}`")

        print(f"| `{method}` | `{path}` | {', '.join(guards) or '—'} |")


if __name__ == "__main__":
    main()
