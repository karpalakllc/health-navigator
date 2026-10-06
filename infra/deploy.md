# Deploy runbook (R1 — PaaS-first)

Simple **deploy-first** topology for public launch. Not Kubernetes.

## Topology

| Component | Suggested host | Notes |
|-----------|----------------|-------|
| **Web** (`apps/web`) | Vercel, Netlify, or a fixed-egress host | Set `NEXT_PUBLIC_API_URL` to public API URL; see **Trusted proxies** before choosing a serverless host |
| **API** (`apps/api`) | Laravel Forge, Laravel Cloud, Railway, Fly.io | PHP 8.4 or 8.5 (`composer.json` requires `^8.4`; CI tests both, 8.5 is the deploy target), `public/` as web root |
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
2. **Once per environment**, generate the application key and store it in the
   host's secret manager as `APP_KEY`:

   ```bash
   php artisan key:generate --show
   ```

   Never run `key:generate` as part of a build or deploy: a new key logs every
   admin out and makes every encrypted value unreadable. Staging and production
   get different keys.
3. Set the remaining environment variables on the API host (never commit secrets) —
   start from [env.production.example](./env.production.example) /
   [env.staging.example](./env.staging.example).
4. Deploy code; `composer install --no-dev --optimize-autoloader`.
5. `php artisan config:cache` and `php artisan route:cache`.
6. **Required:** `php artisan platform:preflight`. It checks the cached
   configuration — APP_KEY, debug off, https URLs, `TRUSTED_PROXIES`, mail
   transport, queue and cache drivers, secure and encrypted session, CORS origins,
   token expiry, the admin address and any `PLATFORM_ADMIN_PASSWORD` left set (it
   must pass the password rule), demo seeding, Meilisearch credentials,
   object-storage media credentials — and exits non-zero on any error. Do not
   migrate or send traffic until it passes. Warnings (Sentry DSN, the local
   `public` media disk, `LOG_LEVEL=debug`) do not fail it but should be read.
   Add `--json` for machine-readable output in a deploy script.
7. First deploy on a fresh database: `php artisan platform:bootstrap` (migrations, RBAC, default site settings, admin user). Set **`PLATFORM_ADMIN_EMAIL`** and **`PLATFORM_ADMIN_PASSWORD`** first — the command creates the admin from them and fails with a clear error if the password is unset. Subsequent deploys: `php artisan migrate --force` only.
   **Guidance flow (once, fresh environment only):** the Macedonian symptom
   guidance flow (`/guidance`) ships as data in `TriageSeeder`, which
   `platform:bootstrap` does **not** run, so a fresh environment has no guidance
   flow to serve. Right after bootstrap, before anyone
   edits guidance content in the admin panel, run it once:
   `php artisan db:seed --class=TriageSeeder --force`.
   **Never run it again on an environment that is in use:** it rewrites the
   flow's title and intro, every red flag, step, option and outcome it knows
   (by code) back to the shipped copy, and **deletes all of the flow's rules**
   before recreating its own — admin edits to those rows are lost and any rule
   added in the panel disappears. (The flow is found by its shipped title; if
   an admin renamed it, re-seeding creates a second published flow instead.)
   Copy changes after go-live are made in the
   admin panel (or by a reviewed migration), not by re-seeding. To check
   whether a flow already exists: `php artisan tinker --execute="echo App\\Models\\TriageFlow::count();"`.
8. When `MEDIA_DISK=public` (persistent volume, not object storage): `php artisan storage:link` once, or uploaded logos and avatars 404.
9. Start a **queue worker** (see below).
10. Add **scheduler** cron (see below).
11. `php artisan search:reindex` when `SCOUT_DRIVER=meilisearch` (after content import).
12. Verify `GET /api/v1/health` and Filament login.

**Seed safety:** `PlatformUserSeeder`, `DoctorDirectorySeeder`, and other directory seeders **only run in `local`, `testing` and `development`** (or with `SEED_LOCAL_DEMO=true`). Do not rely on them in staging/prod except via intentional imports — and never set `SEED_LOCAL_DEMO=true` in production, which would also create demo moderator/member accounts with weak passwords. `platform:bootstrap` creates the production admin itself; it does not depend on the seeder.

**Client addresses:** the web tier vouches for the visitor's address on every
server-side API call (route handlers *and* server rendering) by sending
`X-Client-IP` together with `X-Web-Tier-Auth: <WEB_TIER_SECRET>`. The API accepts
`X-Client-IP` only when that secret matches (`TrustWebTierClientIp`); from anyone
else the header is stripped and ignored.

1. Generate the secret once per environment: `openssl rand -hex 32` (≥32 chars).
2. Set `WEB_TIER_SECRET` to the **same value** on the API (then `config:cache`) and
   on the web server as a runtime, server-only variable — never `NEXT_PUBLIC_`. On
   Vercel, a non-public env var for Production and Preview.
3. Rotate by updating both tiers and restarting. During a mismatch the web tier
   is metered as a single client: it fails safe, with no bypass.

The web tier takes the visitor's address from the **rightmost**
`x-forwarded-for` entry by default — correct behind a single appending or
overwriting edge (nginx, Vercel, Fly, Render). Set `CLIENT_IP_HEADER` on the web
app only to a header your edge is known to **overwrite** (`cf-connecting-ip` when
the origin accepts traffic solely from Cloudflare); otherwise two-hop setups such
as Cloudflare → nginx → Next meter visitors per Cloudflare PoP. If `next start` is
exposed to the internet with no proxy in front, set `CLIENT_IP_HEADER=none`.

**Trusted proxies:** set `TRUSTED_PROXIES` (see `env.production.example`) to the
exact CIDR ranges of **the API's own edge/load balancer**. The web tier does not
need to be listed — it authenticates with the shared secret instead, which is
what makes serverless web hosts (Vercel, Netlify) with dynamic egress workable.
There is deliberately **no default**: unset collapses every IP-based limit on
direct browser→API calls (guidance/triage, media) into one bucket behind the edge.

**Never use `*`** (or `**`, `0.0.0.0/0`), not even when the origin is reachable
only through the edge. With every hop trusted, Laravel takes the *leftmost*
`X-Forwarded-For` entry as the client — and the edge appends to whatever the client
sent, so that entry is attacker-chosen. Any caller then mints a fresh bucket for
every IP-keyed limiter: the 40/min per-address login limiter, registration,
reviews, triage. (The per-account failed-login lockout — 5 failures per minute,
keyed on email *and* address — is affected too, since its address half is spoofed.)
`platform:preflight` rejects these values, and fails when `WEB_TIER_SECRET` is
missing or shorter than 32 characters.

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

1. Set `NEXT_PUBLIC_API_URL` to the public API URL and `NEXT_PUBLIC_SITE_URL` to the
   public web origin (both at build time — see below).
   If the same build is also reached from other origins (apex and www without a
   redirect, a staging alias), list them in `ALLOWED_ORIGINS` — comma-separated,
   server-only, read at runtime — or every sign-in, logout and form submitted from
   them is refused with 403. On Vercel the deployment's own `VERCEL_URL` and
   `VERCEL_BRANCH_URL` are accepted automatically, so preview URLs work.
2. Optional analytics: `NEXT_PUBLIC_PLAUSIBLE_DOMAIN`. For self-hosted Plausible also
   set `NEXT_PUBLIC_PLAUSIBLE_SCRIPT_URL` and `NEXT_PUBLIC_PLAUSIBLE_HOST` to the same
   origin — the script loads from the first, the CSP allows the beacon only to the second.
   The script must be a **manual** build (`script.manual.js`, or a `script.manual.*.js`
   variant): the web app sends pageviews itself with the query string stripped, and
   refuses to load any other build, since those report full URLs (`?q=` searches) on their own.
3. Set Sentry: `NEXT_PUBLIC_SENTRY_DSN`, `SENTRY_DSN`, `NEXT_PUBLIC_SENTRY_ENVIRONMENT` / `SENTRY_ENVIRONMENT`.
   For readable production stack traces also set `SENTRY_ORG`, `SENTRY_PROJECT` and
   `SENTRY_AUTH_TOKEN` in the **build** environment so source maps are uploaded.
4. Build: `npm ci && npm run build`.
5. Verify home, `/register`, `/doctors`, `/forum`, `/privacy`.

> **`NEXT_PUBLIC_SITE_URL` must be set at _build_ time**, not only at runtime.
> `NEXT_PUBLIC_*` is inlined into the bundle, so setting it afterwards leaves
> localhost in `robots.txt`, the sitemap and every canonical URL. A production
> build without it now fails rather than shipping that silently.

## CORS

API must allow the web origin(s). In `apps/api/.env`:

```env
CORS_ALLOWED_ORIGINS=https://staging.example.com,https://www.example.com
```

Local defaults remain in `config/cors.php` (`localhost:3000`).

## Media on object storage

Object storage is the recommended media disk for any deployment: the local `public`
disk only survives on a persistent volume. `MEDIA_DISK=s3` works with any
S3-compatible store — AWS S3, Cloudflare R2, Backblaze B2, MinIO. Variables (see
[env.production.example](./env.production.example)):

| Variable | Purpose |
| --- | --- |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | A key scoped to this bucket: put, get, delete. |
| `AWS_DEFAULT_REGION`, `AWS_BUCKET` | `auto` for R2. |
| `AWS_ENDPOINT` | Non-AWS only: the provider's S3 API endpoint. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` for MinIO. |
| `AWS_URL` | Public https base that media URLs are built from: a CDN, or the bucket's public domain. Required with `AWS_ENDPOINT` — an R2/B2 API endpoint is not publicly readable. |
| `MEDIA_VISIBILITY` | The env examples set `private`: no ACL is sent, and read is granted with a bucket policy (below) — what new AWS buckets and R2 require. The config default is `public`, which suits the local disk; on S3 it sends the `public-read` ACL, so use it only for a bucket with ACLs enabled. |

`platform:preflight` fails when the bucket, region or keys are missing or `AWS_URL`
is not https, and warns when `AWS_ENDPOINT` is set without `AWS_URL`.

**What is written.** Each upload gets a new random filename and is never rewritten
(a replacement is a new object; the old one is deleted). Every object is stored with
an explicit `Content-Type` (`image/webp`, `image/svg+xml`, …), `Content-Disposition:
inline` and `Cache-Control: public, max-age=31536000, immutable`
(`MEDIA_CACHE_CONTROL`). So a CDN in front of the bucket can cache indefinitely, and
nothing needs purging.

**Public read.** Grant anonymous `s3:GetObject` on the media prefix only (default
`media/`, `MEDIA_DIRECTORY`) — never list, and never the whole bucket:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": "*",
    "Action": "s3:GetObject",
    "Resource": "arn:aws:s3:::YOUR-BUCKET/media/*"
  }]
}
```

New AWS buckets have *Block Public Access* on and ACLs disabled ("bucket owner
enforced"): allow the policy above under Block Public Access, and set
`MEDIA_VISIBILITY=private` (a `public-read` ACL is rejected). On R2, connect a custom
domain (or enable r2.dev) for the bucket and use it as `AWS_URL`.

**CORS is not needed.** The site only renders media with plain `<img>` and the
browser fetches them without CORS. Add a bucket CORS rule only if a future feature
reads image bytes from script (canvas, `fetch`).

**Web tier.** Set `NEXT_PUBLIC_MEDIA_URL` on the web build to the same value as
`AWS_URL`. Its origin is added to the CSP `img-src` (`apps/web/src/proxy.ts`), and a
path-scoped `images.remotePatterns` entry is added (`apps/web/next.config.ts`).
Production requires https, and an invalid value fails the build. Without it, every
logo and avatar from the bucket is blocked by the CSP. It is inlined at build time,
so change it with a rebuild.

**Switching an existing deployment.** Copy `storage/app/public/media` into the
bucket under the same `media/` keys first. Rows that stored a full
`…/storage/media/…` URL on the API host (or on localhost) are rebased onto the bucket
automatically. Other hosts are left as they are.

## Transactional email

Password reset, email verification, the welcome mail and the UGC
submitted/approved/rejected lifecycle all depend on a working transport. Mail sent
through the queue does not surface a misconfigured transport as a request error:
the job lands in `failed_jobs` and the user simply never receives anything.

`MAIL_SCHEME` is Symfony Mailer's scheme, not a TLS mode: use `smtp` (port 587,
STARTTLS negotiated automatically) or `smtps` (port 465). `tls` and `ssl` are
rejected and every send fails; `platform:preflight` checks this.

Before launch:

1. Set the `MAIL_*` variables (see [env.production.example](./env.production.example)).
   Sign-up mail is rate limited per address (one per minute, six per hour) across
   every path that can trigger it, so a stranger cannot flood someone's inbox by
   repeatedly submitting their address.
2. Publish **SPF**, **DKIM** and **DMARC** records for the sending domain. Without
   them, password-reset mail lands in spam, which is an account-loss event.
3. Monitor `failed_jobs` and alert on it — this is the only signal that mail is broken.
4. Verify end to end on staging: request a password reset and complete it.

**Trusted hosts:** outside `local`, the app rejects requests whose `Host` is not
`APP_URL`'s domain (or a subdomain). This is what stops a forged host being used
to mint verification links on an attacker's domain. It makes **`APP_URL` a
required, correct value** — if it is wrong, legitimate requests are refused.
Asset and signed-link generation still follow the (now validated) request host,
so serving the admin on a different port in development continues to work.
Queued mail is the exception: the worker has no request, so verification links
are built from `APP_URL` alone — it must be the exact public API origin
(scheme, host and any port), or every emailed link fails its signature check.

> A wrong `APP_URL` in production therefore rejects **every** request with a 400,
> not just signed links — loud rather than subtle, but check it first if a fresh
> deploy answers nothing. The rule is off in `local` and in tests, so this only
> bites in staging and production.

## Pre-deploy data checks

Two one-off checks before the first deploy of the Part I remediation:

- **Featured demo rows.** `2026_05_23_100000_mark_homepage_featured_demo` is now
  guarded to non-production, but the guard cannot undo a database where it already
  ran. Confirm `doctors.is_featured` / `facilities.is_featured` are not set on real
  records that happen to share a demo slug:

  ```sql
  select slug from doctors where is_featured;
  select slug from facilities where is_featured;
  ```

  *Checked on the local development database: the four featured doctors are
  exactly the demo slugs, no facilities are featured, and nothing real was
  promoted. No staging or production database exists yet.*

- **Unverified accounts.** Login refuses accounts with a null
  `email_verified_at`. `2026_08_15_100000_verify_accounts_predating_email_verification`
  grandfathers everything that predates the deploy and prints how many it touched
  — read that line rather than assuming it was zero.

  *Checked locally: three accounts had a null value. Two were seeded demo members
  that `RichDemoSeeder` created without one, which meant a fresh seed produced
  accounts that could not sign in; the seeder now sets it. A fresh seed leaves
  zero, and all four demo logins return a token.*

## TLS and secrets

- TLS terminated at the PaaS edge (required for production). `SESSION_SECURE_COOKIE=true`.
- One `APP_KEY` per environment, generated once (see deploy step 2); never reuse the production key in staging.
- Use strong unique passwords for staff accounts (Filament).
- **Rotating `APP_KEY`:** staff two-factor secrets and recovery codes are encrypted with it. Move the old key into `APP_PREVIOUS_KEYS` when rotating, or every enrolled account is locked out of the panel.

## Admin panel: two-factor, session, headers

- **Two-factor is mandatory for anyone holding `admin.access`** (Administrator, Moderator). On first sign-in they land on `/admin/multi-factor-authentication/set-up` and must scan the QR code with an authenticator app, confirm a code and their password, and save the 8 one-time recovery codes (shown once). Community moderators may enrol from `/admin/profile`; it is optional for them. Setting up, regenerating recovery codes and disabling all ask for the current password. There is no config switch to turn this off; `platform:preflight` fails if the panel wiring is removed.
- **Lost authenticator and recovery codes:** an administrator clears the two columns for that account (`users.app_authentication_secret`, `users.app_authentication_recovery_codes` set to `NULL`) in a database session; the user enrols again on next sign-in.
- **After deploying two-factor, have every staff member sign in and enrol immediately** — until they do, their account is password-only.
- **API sign-in is refused** (`403 auth.staff_use_admin`) for `admin.access` holders; staff work in the panel. Two-factor protects the panel only: a community moderator who opted in still signs in to the public website with their password. Tokens an account already holds are refused (not deleted) while it holds `admin.access` — including ones issued before a promotion — and work again after demotion until they expire. Enrolling deletes the API tokens of an `admin.access` holder.
- **No "remember me":** the panel's login has no remember option, remember cookies are ignored, and the migration that removed it cleared every stored remember token. When the session's idle timeout passes, staff sign in again with password and second factor.
- **Session:** the panel is the only session user. `SESSION_LIFETIME` is its idle timeout, default 60 minutes; preflight refuses more than 60, a `SESSION_SAME_SITE` other than `lax`/`strict`, and `SESSION_HTTP_ONLY=false`. `SESSION_EXPIRE_ON_CLOSE` stays false by default (the idle timeout is the control).
- **Security headers** (`SetSecurityHeaders`, global): `nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, a minimal `Permissions-Policy`, `X-Frame-Options: DENY` everywhere. `/api/*` gets `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'`. The panel gets an enforced CSP of `frame-ancestors`/`base-uri`/`object-src`/`form-action` only; the full fetch policy (Filament needs `'unsafe-inline'` and `'unsafe-eval'` for scripts) is sent as `Content-Security-Policy-Report-Only` with no report endpoint — check the browser console on the panel after a Filament upgrade and promote it to enforced once clean. HSTS (`max-age=31536000`, no `includeSubDomains`) is sent only over HTTPS in deployed environments. No `Cross-Origin-Resource-Policy`: the web app embeds media from another origin. `/storage` and build assets are served by the web server, not Laravel, so add headers there at the edge if wanted.

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
