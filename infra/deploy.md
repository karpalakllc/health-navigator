# Deploy runbook (R1 — PaaS-first)

Simple **deploy-first** topology for closed beta. Not Kubernetes.

## Topology

| Component | Suggested host | Notes |
|-----------|----------------|-------|
| **Web** (`apps/web`) | Vercel, Netlify, or similar | Set `NEXT_PUBLIC_API_URL` to public API URL |
| **API** (`apps/api`) | Laravel Forge, Laravel Cloud, Railway, Fly.io | PHP 8.3+, `public/` as web root |
| **PostgreSQL** | Managed DB from API host or Neon/DO | Same region as API when possible |
| **Admin** | Same host as API | `/admin` (Filament) |

## Environments

| | Staging | Production |
|--|---------|------------|
| Purpose | QA, beta testers | Closed beta / pre-launch |
| `APP_ENV` | `staging` | `production` |
| `APP_DEBUG` | `false` | `false` |
| DSNs | Separate Sentry projects or environments | Separate from staging |

See [env.staging.example](./env.staging.example) and [env.production.example](./env.production.example).

## Deploy order (API)

1. Provision PostgreSQL; create database and user.
2. Set environment variables on the API host (never commit secrets).
3. Deploy code; `composer install --no-dev --optimize-autoloader`.
4. `php artisan migrate --force` (no `db:seed` in production unless importing real content).
5. `php artisan config:cache` and `php artisan route:cache` when stable.
6. Verify `GET /api/v1/health` and Filament login.

**Seed safety:** `PlatformUserSeeder`, `DoctorDirectorySeeder`, and other directory seeders **only run in `local` and `testing`**. Do not rely on them in staging/prod.

## Deploy order (Web)

1. Set `NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_CLOSED_BETA=true` for closed beta.
2. Set Sentry: `NEXT_PUBLIC_SENTRY_DSN`, `SENTRY_DSN`, `NEXT_PUBLIC_SENTRY_ENVIRONMENT` / `SENTRY_ENVIRONMENT`.
3. Build: `npm ci && npm run build`.
4. Verify home, `/privacy`, `/login`, `/guidance`.

## CORS

API must allow the web origin(s). In `apps/api/.env`:

```env
CORS_ALLOWED_ORIGINS=https://staging.example.com,https://www.example.com
```

Local defaults remain in `config/cors.php` (`localhost:3000`).

## TLS and secrets

- TLS terminated at the PaaS edge (required for beta).
- Rotate `APP_KEY` per environment; never reuse production key in staging.
- Use strong unique passwords for staff and beta members (Filament user create).

## Backups

- Enable automated daily backups on managed PostgreSQL.
- Document restore drill before inviting testers.

## Health checks

- Monitor `GET {API_URL}/api/v1/health` (expect `{"data":{"status":"ok"}}`).
- Monitor web `/` availability.
- Sentry alerts on new issues (staging vs production separated).

## Closed beta

See [docs/beta-closed.md](../docs/beta-closed.md). No public registration; members provisioned in Filament.
