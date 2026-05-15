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
| Database | PostgreSQL | Configured in API (`.env.example`); run Postgres locally |
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
- **PostgreSQL** for the API (create database `zdravje360`; see `apps/api/.env.example`)
- Redis and Meilisearch for full local parity — not wired yet ([TASKS.md](./TASKS.md))

SQLite is **not** the project’s local application database. PHPUnit may still use in-memory SQLite for tests only.

## Local development

Default URLs: API `http://localhost:8000`, web `http://localhost:3000`. The web app calls the API directly (no BFF).

### API — PostgreSQL setup (macOS)

Prerequisites: `pdo_pgsql` enabled (`php -m | grep pgsql`). Copy `apps/api/.env.example` to `apps/api/.env` if you do not have one yet; `php artisan key:generate` if `APP_KEY` is empty.

**1. Start or verify PostgreSQL** (use the option that matches your install):

```bash
# Check if something is listening on 5432
nc -zv 127.0.0.1 5432

# Homebrew PostgreSQL (service name may be postgresql@16, postgresql@17, etc.)
brew services list | grep postgres
brew services start postgresql@16   # adjust version if needed

# Postgres.app (CLI not on PATH): open the app, then use its bundled tools, e.g.
# /Applications/Postgres.app/Contents/Versions/latest/bin/pg_isready -h localhost

# Docker (if you use a local Postgres container on 5432)
# docker start <your-postgres-container>
```

**2. Create the database** (pick one `psql` invocation that works on your machine):

```bash
# Homebrew / PATH: often user `postgres` or your macOS username
createdb zdravje360
# or
psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE zdravje360;"
# or (macOS login role, peer/trust on socket)
psql -d postgres -c "CREATE DATABASE zdravje360;"
```

**3. Align `apps/api/.env`** with your server (defaults in `.env.example`):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=zdravje360
DB_USERNAME=postgres
DB_PASSWORD=
```

If `php artisan migrate` fails with `fe_sendauth: no password supplied`, set `DB_PASSWORD` to your local Postgres password, or change `DB_USERNAME` to the role your install expects (often your macOS username for Homebrew).

**4. Run the API**

```bash
cd apps/api
composer install
php artisan migrate
php artisan serve
```

**5. Verify health**

```bash
curl -s http://127.0.0.1:8000/api/v1/health
# Expected: {"status":"ok"}
```

`SESSION_DRIVER`, `CACHE_STORE`, and `QUEUE_CONNECTION` use the database; default migrations create `sessions`, `cache`, and `jobs` tables on PostgreSQL.

**Web**

```bash
cd apps/web
npm install
npm run dev
```

The web starter does not call the API yet; use the health URL above or `curl` to verify the API. CORS allows `localhost:3000` and `127.0.0.1:3000` for future browser calls.

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
