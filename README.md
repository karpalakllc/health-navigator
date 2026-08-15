# Zdravje360

Web-first medical platform for North Macedonia — doctor directory, healthcare facilities, pharmacy catalog, community forum, and admin-managed content. Mobile apps will consume the same API later.

**Status:** **MVP product finalization** is complete ([mvp acceptance](docs/mvp-acceptance.md)). **Next:** [beta verification](docs/beta-verification.md) and R1 ops (staging deploy) before external invite-only testers — see [TASKS.md](./TASKS.md). **Planned web discovery / search evolution:** [docs/frontend-discovery-content-plan.md](docs/frontend-discovery-content-plan.md) (Track W7). **Fresh chat / handoff:** [docs/continuity-handoff.md](docs/continuity-handoff.md). API: [docs/api-contract.md](./docs/api-contract.md). Guidance safety: [docs/triage-safety.md](docs/triage-safety.md).

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
| API | Laravel 13, PHP 8.5+ | `/api/v1`, Sanctum tokens, JSON envelope |
| Admin | Filament at `/admin` (session) | Phase 2 baseline |
| Web | Next.js 16, React 19, Tailwind 4 | Public directories, login, reviews (cookie auth bridge) |
| Database | PostgreSQL | Runtime standard; see `apps/api/.env.example` |
| Cache / queues | Redis | Planned |
| Search | Meilisearch | Planned |
| Mobile | Same REST/JSON API | Future |

## Architecture (summary)

- **API-first:** `apps/web` calls `apps/api` directly from the browser or server components — no default Next.js BFF layer.
- **Optional later:** BFF or proxy routes only for specific cases (e.g. SSR session aggregation), not the default pattern.
- **Primary locale:** Macedonian at launch; English may follow later.
- **Details:** [docs/architecture.md](./docs/architecture.md)

## Prerequisites

- PHP 8.5+, [Composer](https://getcomposer.org/)
- Node.js 24+ (for `apps/web`) — see below if your machine defaults to an older Node

### Node 24 without disturbing your other projects

`apps/web` requires Node 24 (declared in `engines`; Node 20 went end-of-life in
April 2026). If your machine's default `node` is older and other projects on it
depend on that older version, install Node 24 *keg-only* so nothing global
changes:

```sh
brew install node@24          # keg-only: does NOT relink /opt/homebrew/bin/node
```

Then put it first on PATH only while working in this repo:

```sh
export PATH="/opt/homebrew/opt/node@24/bin:$PATH"
```

Your default `node` stays exactly where it was, so every other project resolves
the same version it always did. `.nvmrc` pins 24 for anyone using nvm or fnm.
- **PostgreSQL** for the API (create database `zdravje360`; see `apps/api/.env.example`)
- Redis and Meilisearch for full local parity — not wired yet ([TASKS.md](./TASKS.md))

SQLite is **not** the project’s local application database. PHPUnit may still use in-memory SQLite for tests only.

## Local development

| App | URL | Env file |
|-----|-----|----------|
| API | http://127.0.0.1:8000 | `apps/api/.env` (from `.env.example`) |
| Web | http://127.0.0.1:3000 | `apps/web/.env.local` (from `.env.example`) |

| Variable | App | Purpose |
|----------|-----|---------|
| `DB_*` | API | PostgreSQL connection |
| `NEXT_PUBLIC_API_URL` | Web | Laravel API base URL (no trailing slash) |

The web app calls the API directly (no BFF). **Docker Postgres:** if you use a container on port 5432 (e.g. `qss-postgres`), set `DB_USERNAME` / `DB_PASSWORD` in `apps/api/.env` to match the container — see comment in `apps/api/.env.example`.

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

Run **`php artisan migrate` before `db:seed`**: profile fields (doctor/facility `office_hours`, `avatar_url`, languages, etc.) come from `2026_05_21_120000_add_profile_fields_to_doctors_and_facilities.php`. Check with `php artisan migrate:status` (no pending migrations).

**5. Verify health**

```bash
curl -s http://127.0.0.1:8000/api/v1/health
# Expected: {"data":{"status":"ok"}}
```

`SESSION_DRIVER`, `CACHE_STORE`, and `QUEUE_CONNECTION` use the database; default migrations create `sessions`, `cache`, and `jobs` tables on PostgreSQL.

**Both apps (recommended)**

```bash
chmod +x scripts/dev.sh   # once
./scripts/dev.sh
```

Or two terminals: `cd apps/api && php artisan serve` and `cd apps/web && npm run dev`.

Open http://127.0.0.1:3000 (web) and http://127.0.0.1:8000/api/v1/health (API). Browse `/forum` after `php artisan db:seed`. **If `/doctors`, `/facilities`, etc. are empty:** the API database has no published rows (seed not run, or seeders were skipped). From `apps/api` run `php artisan migrate:fresh --seed`, or if `APP_ENV` is not `local`/`development`, set `SEED_LOCAL_DEMO=true` in `apps/api/.env` and run `php artisan db:seed`. Ensure `NEXT_PUBLIC_API_URL` in `apps/web/.env.local` matches your API (e.g. `http://127.0.0.1:8000`). **Keep both dev processes running** (`./scripts/dev.sh` or two terminals).

## Phase 2 — Auth and admin (local)

After pulling RBAC/settings changes, bootstrap the API database once:

```bash
cd apps/api && php artisan platform:bootstrap
php artisan serve --port=8000
```

This runs migrations, site settings, permissions, and ensures the admin user has the **Administrator** role (`admin.access`).

| | |
|--|--|
| Filament admin | http://127.0.0.1:8000/admin — `admin@zdravje360.test` / `password` (see `PLATFORM_*` in `.env.example`) |
| Filament user CRUD | **Admin role only** (moderators cannot manage users/roles) |
| API login | `POST /api/v1/auth/login` → Bearer token (no web session created) |
| Contract | [docs/api-contract.md](./docs/api-contract.md) |

Default seeded passwords in `.env.example` are for **local development only**. Change them before any shared or production environment.

## CI

On push/PR to `main`, [`.github/workflows/ci.yml`](./.github/workflows/ci.yml) runs Laravel tests and Next.js lint + build.

## Documentation

| Document | Contents |
|----------|----------|
| [PROJECT_BRIEF.md](./PROJECT_BRIEF.md) | Product vision, domains, constraints, non-goals |
| [docs/architecture.md](./docs/architecture.md) | System design, boundaries, target services |
| [docs/frontend-ui-transformation.md](./docs/frontend-ui-transformation.md) | Target public UI/UX (shell, pages, search overlay, T1–T4) |
| [docs/frontend-discovery-content-plan.md](./docs/frontend-discovery-content-plan.md) | Discovery & content depth, unified search vs advanced modal, directory filters (Track W7) |
| [TASKS.md](./TASKS.md) | Backlog, beta checklist, roadmap phases R1–R8 |
| [docs/beta-verification.md](./docs/beta-verification.md) | Pre-invite verification checklist |
| [docs/roadmap.md](./docs/roadmap.md) | One-page roadmap summary |
| [.cursor/rules/project.mdc](./.cursor/rules/project.mdc) | Cursor agent conventions for this repo |

## Out of scope (current phase)

Complete [docs/beta-verification.md](./docs/beta-verification.md) before external testers. Do not implement yet: Meilisearch (R3), sponsorships (R4), triage **AI** (G / 3f-b), mobile (R8), or checkout.

## Contributing

1. Read `PROJECT_BRIEF.md` and `docs/architecture.md` before structural or cross-app changes.
2. Keep changes scoped to the relevant app (`apps/api` vs `apps/web`).
3. Do not add dependencies without an agreed task in `TASKS.md`.
4. Prefer small PRs aligned with the active phase in `TASKS.md`.
