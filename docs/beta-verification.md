# Pre-launch verification checklist

Practical checklist before **public launch** or a **wider beta cohort**. Work through each section on the **same staging URL** you plan to ship (or production, if that is the first public environment).

**Related:** [pre-launch-master-plan.md](./pre-launch-master-plan.md) · [mvp-acceptance.md](./mvp-acceptance.md) · [beta-content-readiness.md](./beta-content-readiness.md) · [infra/deploy.md](../infra/deploy.md) · [triage-safety.md](./triage-safety.md)

**Launch mode (current):**

- **Open registration** with an admin toggle (`registrations_enabled` in Filament → Site settings).
- **Public surface at launch:** doctors, facilities, forum, search, accounts, legal pages.
- **Hidden until enabled:** symptom guidance (`public_guidance`), products (`public_products`), pharmacies (`public_pharmacies`) — visitors see **coming soon** (blur), API returns **503** for those modules when disabled.

**Obsolete for this checklist:** [beta-closed.md](./beta-closed.md) (invite-only Path B) — kept for history only.

---

## Required before go-live

### Legal

- [ ] `/privacy`, `/terms`, `/disclaimer` reviewed and **approved by counsel** (no “Нацрт” / DRAFT banners on pages)
- [ ] Cookie notice acceptable inside privacy (no separate `/cookies` unless legal adds one later)
- [ ] Forum and review disclaimers visible where users submit UGC
- [ ] If guidance is enabled later: copy does not claim diagnosis or emergency dispatch ([triage-safety.md](./triage-safety.md))

**DRAFT legal copy:** OK for **internal/staging QA only**. **Not OK** for a public production URL.

### Platform settings (Filament → Site settings)

Confirm on staging with launch-intended values:

- [ ] `registrations_enabled` — **on** when you want public sign-up; **off** to hide register CTAs and block `POST /auth/register`
- [ ] `public_guidance` — **off** at launch (unless product explicitly enables)
- [ ] `public_products` — **off** at launch
- [ ] `public_pharmacies` — **off** at launch
- [ ] `public_forum` — **on**

### Staging works end-to-end

- [ ] `GET {API_URL}/api/v1/health` returns healthy payload over HTTPS (DB + optional Redis/Meilisearch flags as deployed)
- [ ] Web home loads over HTTPS; `NEXT_PUBLIC_API_URL` points at that API
- [ ] **Footer links** to Privacy, Terms, Disclaimer work on the deployed URL
- [ ] **Register** → creates member account (when registrations enabled) → lands in account
- [ ] **Login** → account overview (no CORS errors in browser console)
- [ ] **Forgot password** → email received (mail trap in staging) → **reset password** link opens `/reset-password` and completes
- [ ] Submit **review** on doctor/facility → **pending** in Filament → approve → visible on profile + author receives approval email (if mail queue running)
- [ ] Submit **forum topic** → **pending** in Filament → approve → visible on site + author notified
- [ ] **Unified search** (`/search` or header): returns doctors, facilities, forum; **no** products/pharmacies while those flags are off
- [ ] **Forum** home search (`/forum?q=…`) returns cross-category topics
- [ ] **Coming soon:** `/guidance`, `/products`, `/products/{slug}`, `/pharmacies`, `/pharmacies/{slug}` show blur shell, not live catalog/triage (with flags off)
- [ ] Home **does not** show product rail or quick actions for disabled modules (matches settings)

### Content

- [ ] Staging has **real or realistic** doctors and clinical facilities (not only placeholder dev names)
- [ ] Doctor↔facility links work both ways
- [ ] Optional: pharmacy/product records in admin for future launch — not required for public QA while hidden

**Content language:** UI chrome is MK (`apps/web/src/i18n/mk.ts`). Database text may be EN/Latin — document in [beta-content-readiness.md](./beta-content-readiness.md).

### Moderation & community

- [ ] Named **staff moderators** with Filament access (staff user + appropriate role/permissions)
- [ ] **Moderation SLA** documented (e.g. pending queue within N business days)
- [ ] Dry run: approve/reject review and forum topic; rejection note visible to author where implemented
- [ ] Forum **community rules** visible on forum pages

### Email & queues (staging)

- [ ] `FRONTEND_URL` / `WEB_PUBLIC_URL` set to the public web origin (reset links, moderation emails)
- [ ] Mail driver configured (SMTP/Postmark/etc.) or mail trap in staging
- [ ] **Queue worker** running if using queued mail (`WelcomeMail`, `UgcApprovedMail`, etc.)
- [ ] Test: register (welcome), forgot password (reset link), approve UGC (approval mail)

### Search (optional but recommended)

- [ ] Meilisearch reachable; `SCOUT_DRIVER=meilisearch` in API env
- [ ] `php artisan search:reindex` run after content load
- [ ] If Meilisearch down: SQL fallback still returns results (degraded, not empty)

### Security & observability

- [ ] **No default seed credentials** on staging/prod (`PlatformUserSeeder` / demo seeders only in `local`/`testing`)
- [ ] Staff/admin passwords strong and unique (not `password` from `.env.example`)
- [ ] **Sentry** receives test events for API and web (correct environment tag)
- [ ] **Health/uptime** monitor on API health and web `/`
- [ ] Security headers present on web responses (`X-Frame-Options`, `X-Content-Type-Options`, etc.)

---

## Repo verification (before or with staging deploy)

Run locally or in CI — does not replace staging checks.

| Check | How | Pass |
|-------|-----|------|
| API tests | `cd apps/api && php artisan test` | All green |
| Web build | `cd apps/web && npm run lint && npm run build` | No errors |
| CI | Latest `main` workflow | Green |
| Register + reset | `/register`, `/forgot-password`, `/reset-password` routes in build output | Present |
| Settings API | `GET /api/v1/settings/public` | Returns module flags |
| Seed safety | Seeders guard `local`/`testing` only | Code review |

---

## Explicit non-blockers

Do **not** delay launch for:

- Product scraper / public catalog (epic PF)
- Sponsorships (R4)
- AI symptom guidance (G / 3f-b)
- English locale / `next-intl`
- Mobile apps (R8)
- Full MK translation of all database content
- Forum report/flag queue, nested replies, attachments (post-launch P5b+)
- Full CSP hardening (baseline headers are enough for first ship if reviewed)
- Category-scoped community moderators (P7c) unless you need them day one

---

## Lightweight sign-off

| | Name | Date |
|---|------|------|
| Product | | |
| Engineering | | |
| Legal (public launch) | | |
| Ops / moderation | | |

**Go** when all **Required before go-live** boxes are checked. **No-go** if any required item fails.

---

## Suggested order

1. Repo verification (engineering, ~half day)
2. Deploy staging per [infra/deploy.md](../infra/deploy.md) (Redis, queue worker, Meilisearch optional)
3. Filament site settings → launch values; bootstrap admin (`php artisan platform:bootstrap` if fresh DB)
4. Staging end-to-end (auth, UGC, search, coming-soon gates, email)
5. Parallel: legal approval, content load, moderators + SLA
6. Sentry + health checks
7. Sign-off → production deploy or widen beta
