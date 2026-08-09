# Deploy runbook (R1 — PaaS-first)

Simple **deploy-first** topology for public launch. Not Kubernetes.

## Topology

| Component | Suggested host | Notes |
|-----------|----------------|-------|
| **Web** (`apps/web`) | Vercel, Netlify, or similar | Set `NEXT_PUBLIC_API_URL` to public API URL |
| **API** (`apps/api`) | Laravel Forge, Laravel Cloud, Railway, Fly.io | PHP 8.3+, `public/` as web root |
| **PostgreSQL** | Managed DB from API host or Neon/DO | Same region as API when possible |
| **Redis** | Managed Redis or same host | Cache, queues, rate limits, scheduler locks |
| **Meilisearch** | Meilisearch Cloud or self-hosted | Unified search (doctors, facilities, forum topics) |
| **Admin** | Same host as API | `/admin` (Filament) |

## Environments

| | Staging | Production |
|--|---------|------------|
| Purpose | QA before release | Public launch |
| `APP_ENV` | `staging` | `production` |
| `APP_DEBUG` | `false` | `false` |
| DSNs | Separate Sentry projects or environments | Separate from staging |

See [env.staging.example](./env.staging.example) and [env.production.example](./env.production.example).

## Deploy order (API)

1. Provision PostgreSQL, Redis, and Meilisearch; create database and user.
2. Set environment variables on the API host (never commit secrets).
3. Deploy code; `composer install --no-dev --optimize-autoloader`.
4. First deploy on a fresh database: `php artisan platform:bootstrap` (migrations, RBAC, default site settings, admin user). Set **`PLATFORM_ADMIN_EMAIL`** and **`PLATFORM_ADMIN_PASSWORD`** first — the command creates the admin from them and fails with a clear error if the password is unset. Subsequent deploys: `php artisan migrate --force` only.
5. `php artisan config:cache` and `php artisan route:cache` when stable.
6. Start a **queue worker** (see below).
7. Add **scheduler** cron (see below).
8. `php artisan search:reindex` when `SCOUT_DRIVER=meilisearch` (after content import).
9. Verify `GET /api/v1/health` and Filament login.

**Seed safety:** `PlatformUserSeeder`, `DoctorDirectorySeeder`, and other directory seeders **only run in `local`, `testing` and `development`** (or with `SEED_LOCAL_DEMO=true`). Do not rely on them in staging/prod except via intentional imports — and never set `SEED_LOCAL_DEMO=true` in production, which would also create demo moderator/member accounts with weak passwords. `platform:bootstrap` creates the production admin itself; it does not depend on the seeder.

**Trusted proxies:** set `TRUSTED_PROXIES` (see `env.production.example`). Skipping it silently breaks every IP-based rate limit — they all collapse into a single shared bucket behind the edge.

### Queue worker

Set `QUEUE_CONNECTION=redis` (recommended) or `database` if Redis is unavailable.

Example Supervisor program (Forge generates similar):

```ini
[program:zdravje-worker]
command=php /path/to/apps/api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=forge
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/logs/worker.log
```

Restart workers after each deploy.

### Scheduler

Cron (once per minute):

```cron
* * * * * cd /path/to/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks include **triage session purge** (`triage:purge-old-sessions`, daily 03:15, 90-day retention). Requires Redis or another cache store that supports atomic locks when using `onOneServer()`.

### Local Redis

```bash
docker compose -f infra/docker-compose.redis.yml up -d
```

Then in `apps/api/.env`: `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_HOST=127.0.0.1`.

## Deploy order (Web)

1. Set `NEXT_PUBLIC_API_URL` to the public API URL.
2. Optional analytics: `NEXT_PUBLIC_PLAUSIBLE_DOMAIN`.
3. Set Sentry: `NEXT_PUBLIC_SENTRY_DSN`, `SENTRY_DSN`, `NEXT_PUBLIC_SENTRY_ENVIRONMENT` / `SENTRY_ENVIRONMENT`.
4. Build: `npm ci && npm run build`.
5. Verify home, `/register`, `/doctors`, `/forum`, `/privacy`.

## CORS

API must allow the web origin(s). In `apps/api/.env`:

```env
CORS_ALLOWED_ORIGINS=https://staging.example.com,https://www.example.com
```

Local defaults remain in `config/cors.php` (`localhost:3000`).

## TLS and secrets

- TLS terminated at the PaaS edge (required for production).
- Rotate `APP_KEY` per environment; never reuse production key in staging.
- Use strong unique passwords for staff accounts (Filament).

## Backups

- Enable automated daily backups on managed PostgreSQL.
- Document restore drill before launch traffic.

## Health checks

- Monitor `GET {API_URL}/api/v1/health` — expect HTTP 200 and `data.status` of `ok` with `checks.database` (and `checks.redis` when Redis is configured).
- HTTP 503 with `data.status` `degraded` indicates database or Redis failure.
- The health route (and `/api/v1/settings/public`) are **exempt from maintenance mode**, so a 503 there always means real degradation, never planned maintenance. Maintenance-mode 503s on other routes return `{"message": ...}` with no `data` key.
- Monitor web `/` availability.
- Sentry alerts on new issues (staging vs production separated).

## Site settings

Module toggles (guidance, products, pharmacies) and `registrations_enabled` are managed in Filament **Site settings** or via `GET /api/v1/settings/public`. Defaults favor launch: core modules on, deferred modules off.
