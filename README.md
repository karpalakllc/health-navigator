# Zdravje360

Web-first medical platform for North Macedonia — doctor directory, healthcare facilities, pharmacy catalog, community forum, and admin-managed content. Mobile apps will consume the same API later.

**Status:** Monorepo scaffold only. Domain features, Filament, search, and production infra are not implemented yet. See [PROJECT_BRIEF.md](./PROJECT_BRIEF.md) and [docs/architecture.md](./docs/architecture.md) for intent vs current state.

## Getting started

Read [PROJECT_BRIEF.md](./PROJECT_BRIEF.md) and [docs/architecture.md](./docs/architecture.md) before making structural or cross-app changes (new services, API boundaries, auth approach, or shared packages).

## Repository layout

| Path | Purpose |
|------|---------|
| [`apps/api/`](./apps/api/) | Laravel 13 API (source of truth for data and business rules) |
| [`apps/web/`](./apps/web/) | Next.js public frontend |
| [`docs/`](./docs/) | Architecture and design notes |
| [`infra/`](./infra/) | Reserved for future local/devops/deployment assets ([infra/README.md](./infra/README.md)) |
| [`packages/`](./packages/) | Shared libraries (future, if needed) |

## Stack

| Layer | Technology | Status |
|-------|------------|--------|
| API | Laravel 13, PHP 8.3+ | Scaffolded |
| Admin | Filament (on Laravel) | Planned |
| Web | Next.js 16, React 19, Tailwind 4 | Scaffolded |
| Database | PostgreSQL | Planned |
| Cache / queues | Redis | Planned |
| Search | Meilisearch | Planned |
| Mobile | Same REST/JSON API | Future |

## Architecture (summary)

- **API-first:** `apps/web` calls `apps/api` directly from the browser or server components — no default Next.js BFF layer.
- **Optional later:** BFF or proxy routes only for specific cases (e.g. SSR session aggregation), not the default pattern.
- **Primary locale:** Macedonian at launch; English may follow later.
- **Details:** [docs/architecture.md](./docs/architecture.md)

## Prerequisites

- PHP 8.3+, [Composer](https://getcomposer.org/)
- Node.js 20+ (for `apps/web`)
- PostgreSQL, Redis, and Meilisearch for full local parity — wiring is Phase 1 ([TASKS.md](./TASKS.md))

## Local development (scaffold)

Run each app from its directory. Ports and env vars are app defaults until Phase 1 standardizes them.

**API**

```bash
cd apps/api
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan serve
```

**Web**

```bash
cd apps/web
npm install
npm run dev
```

Until the API exposes versioned public endpoints, the web app remains the stock Next.js starter.

## Documentation

| Document | Contents |
|----------|----------|
| [PROJECT_BRIEF.md](./PROJECT_BRIEF.md) | Product vision, domains, constraints, non-goals |
| [docs/architecture.md](./docs/architecture.md) | System design, boundaries, target services |
| [TASKS.md](./TASKS.md) | Phased backlog (foundation before domain work) |
| [.cursor/rules/project.mdc](./.cursor/rules/project.mdc) | Cursor agent conventions for this repo |

## Out of scope (current phase)

Do not implement yet: authentication, doctors, clinics, pharmacy catalog, reviews, forum, symptom triage, sponsorships, or CMS content models. Track work in [TASKS.md](./TASKS.md).

## Contributing

1. Read `PROJECT_BRIEF.md` and `docs/architecture.md` before structural or cross-app changes.
2. Keep changes scoped to the relevant app (`apps/api` vs `apps/web`).
3. Do not add dependencies without an agreed task in `TASKS.md`.
4. Prefer small PRs aligned with the active phase in `TASKS.md`.
