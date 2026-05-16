# Continuity handoff (for humans + AI)

**Purpose:** Reload context after clearing a long chat. Read this first, then [TASKS.md](../TASKS.md) and [pre-launch-master-plan.md](./pre-launch-master-plan.md) for detail.

**Last updated:** 2026-05-16 (post **P7b / P7c / P8.4**, CSP dev fix, commit `c278815` on `main`).

---

## 1. Project snapshot

| Item | Detail |
|------|--------|
| **Product** | Zdravje360 — MK-first health directory (doctors, facilities), community forum, reviews, rule-based symptom guidance (not diagnosis). Pharmacies/products built but **hidden at launch**. |
| **Monorepo** | `apps/api` — Laravel 13 + Filament `/admin`; `apps/web` — Next.js 16; `docs/`, `infra/`. |
| **Repo** | `github.com/karpalakllc/health-navigator`, branch **`main`** (usually in sync with `origin/main`). |
| **Locale** | Macedonian UI in `apps/web/src/i18n/mk.ts` (no `next-intl` unless reprioritized). |
| **Architecture** | API-first: web calls Laravel JSON directly (no default BFF). See [architecture.md](./architecture.md). |

### P0 launch decisions (locked — do not revert without owner)

| Decision | Implementation |
|----------|----------------|
| **Open registration** | `SiteSetting::registrations_enabled`; web `/register`; Filament → Site settings |
| **RBAC** | Spatie permissions + Filament Staff / Clients / Roles resources |
| **Staff vs clients** | `user_kind` on `users`; separate Filament resources |
| **Coming soon at launch** | `public_guidance`, `public_products`, `public_pharmacies` off → web blur shell + API **503** |
| **Public at launch** | Doctors, facilities, forum, search, accounts, legal |

**Obsolete for planning:** invite-only beta ([beta-closed.md](./beta-closed.md)) — use [beta-verification.md](./beta-verification.md) for launch QA.

---

## 2. What is done (recent — this thread + `main`)

### 2.1 Platform & pre-launch (high signal)

| Area | Status | Notes |
|------|--------|--------|
| **Site settings** | Done | Module flags, registrations toggle — Filament |
| **Auth** | Done | Register, login, forgot/reset password (API + web pages) |
| **Coming-soon gates** | Done | List + detail for products/pharmacies; home quick actions respect flags |
| **Meilisearch / Scout** | Done | Unified `GET /search`, `search:reindex`; forum in search |
| **Macedonian search** | Done | Latin/Cyrillic variants on doctors, facilities, specialties (`MacedonianSearchVariants`, `ScriptInsensitiveSearch`) |
| **Transactional email** | Done | Welcome, reset, UGC submitted/approved/rejected (`UgcMailer`, queued mailables) |
| **Filament analytics (P7a)** | Done | Events, 7/30/90-day filter, community chart, top searches |
| **RBAC (P7b)** | Done | Permission catalog, roles seeder, Staff/Clients/Roles; `canAccessPanel` allows community mods without `admin.access` |
| **Forum-scoped mods (P7c)** | Done | `forum_category_moderator` pivot, `ForumModerationScope`, policy + Filament query scoping, action guards |
| **Moderation digest (P8.4)** | Done | `moderation:send-digest`, daily 07:00, `ModerationDigestMail` |
| **CSP (web)** | Done | Security headers in `next.config.ts`; **`unsafe-eval` only in dev** (fixes React dev console error) |
| **Ops** | Partial | Health checks, triage purge command, deploy docs, docker compose notes |

### 2.2 Key commits (newest first)

```
c278815 feat: forum-scoped moderation, daily digest, and dev CSP fix
2dcfaa9 feat: deepen Filament analytics with events, charts, and period filter
49893e4 feat: complete coming-soon gates, UGC email lifecycle, and launch QA
6317dfc feat: pre-launch directory QA, forum search, and transactional email
f92ed50 feat: launch ops, Meilisearch search, and forum community rules
```

### 2.3 Docs added/updated in this arc

- [pre-launch-master-plan.md](./pre-launch-master-plan.md) — owner roadmap (inventory section may lag; trust git + this file)
- [beta-verification.md](./beta-verification.md) — launch QA (open reg + coming-soon modules)
- [community-moderator-onboarding.md](./community-moderator-onboarding.md) — assign Forum Moderator + categories (P7c)

### 2.4 Still solid from earlier MVP

- Filament directory + forum moderation queues, bulk approve/reject
- Public web: directories, forum, guidance (when enabled), account, global search overlay
- CI workflow, Sentry wiring, rich demo seed

---

## 3. What is NOT done / next priorities

Use **[pre-launch-master-plan.md](./pre-launch-master-plan.md)** phases; summary:

| Priority | Work | Doc / command |
|----------|------|----------------|
| **P9 — Launch gate** | Execute [beta-verification.md](./beta-verification.md) on **staging**; legal sign-off on MK copy | Checklist |
| **Deploy** | Staging/production per [infra/deploy.md](../infra/deploy.md); DSNs, mail, queue workers | Ops |
| **P1 infra** | Redis + queue workers in prod; rate limits; runbooks | Partial locally |
| **P8.5** | Notify facility/doctor on new review (rules workshop) | Optional pre-launch |
| **P4 polish** | Doctor/facility SEO, loading states — largely started | Web |
| **P5 forum** | Global forum search done; account forum polish — mostly done | |
| **Content** | Real directory + forum seed on staging; moderation staffing | Ops |
| **Env sync** | After pull: `php artisan db:seed --class=RolesAndPermissionsSeeder` so **Forum Moderator** loses `admin.access` | One-time per env |

**Do not start** without reprioritization: **G (AI triage)**, **R4 sponsorships**, new Composer/npm deps, visual triage rule builder.

---

## 4. Local development (quick)

| App | URL | Credentials (local only) |
|-----|-----|---------------------------|
| Web | http://127.0.0.1:3000 | — |
| API | http://127.0.0.1:8000 | — |
| Filament | http://127.0.0.1:8000/admin | `admin@zdravje360.test` / `password` |

```bash
# From repo root — both apps (or use two terminals)
./scripts/dev.sh

# API bootstrap (migrations, settings, RBAC, admin role)
cd apps/api && php artisan platform:bootstrap

# After RBAC changes
php artisan db:seed --class=RolesAndPermissionsSeeder

# Tests / build
cd apps/api && php artisan test
cd apps/web && npm run build

# Moderation digest dry-run
php artisan moderation:send-digest --dry-run
```

**Web env:** `apps/web/.env.local` → `NEXT_PUBLIC_API_URL=http://127.0.0.1:8000`  
**CSP:** Restart `npm run dev` after `next.config.ts` changes.

---

## 5. Important paths (for the next agent)

| Topic | Path |
|-------|------|
| Permissions | `apps/api/app/Support/PermissionCatalog.php`, `database/seeders/RolesAndPermissionsSeeder.php` |
| Forum scope | `apps/api/app/Support/ForumModerationScope.php`, `app/Models/User.php` (`canModerateForumCategory`) |
| Site flags | `apps/api/app/Models/SiteSetting.php`, Filament site settings page |
| Coming soon (web) | `apps/web/src/components/.../coming-soon-shell.tsx` (grep `ComingSoon`) |
| Search | Meilisearch config, `apps/api/app/Console/Commands/SearchReindexCommand.php` |
| Email | `apps/api/app/Support/UgcMailer.php`, `app/Mail/*`, `config/zdravje.php` (`frontend_url`) |
| Digest | `apps/api/app/Console/Commands/SendModerationDigestCommand.php`, `routes/console.php` |
| CSP | `apps/web/next.config.ts` |
| i18n | `apps/web/src/i18n/mk.ts` |
| Cursor rules | `.cursor/rules/project.mdc` |

---

## 6. How we work (dynamics)

1. Read **PROJECT_BRIEF.md**, **architecture.md**, **TASKS.md** before structural changes.
2. **Active phase:** Pre-launch / **R1 launch** — beta verification + staging deploy, not new R2+ epics.
3. **Scope:** One app per PR when possible; no drive-by refactors; no new deps without approval.
4. **Commits / push:** Only when the user asks.
5. **Medical UX:** No diagnosis/emergency claims; see [triage-safety.md](./triage-safety.md).

---

## 7. Suggested prompt for a fresh chat

Copy and edit:

> Read `docs/continuity-handoff.md`, `TASKS.md`, and `docs/pre-launch-master-plan.md`. We're on `main` (Zdravje360). Pre-launch: open registration, RBAC, coming-soon for guidance/products/pharmacies, Meilisearch search, forum-scoped moderators + daily digest are shipped. Next focus: \<e.g. run beta-verification on staging / P9 deploy / P8.5 review notifications\>.

---

## 8. Known gotchas

- **Forum Moderator role:** Must re-seed permissions on each environment after `c278815` so role does not include `admin.access`.
- **Meilisearch:** Index must exist; run `php artisan search:reindex` when data changes materially.
- **Mail:** Queued mailables need `QUEUE_CONNECTION` + worker in staging/prod.
- **pre-launch-master-plan.md §2 inventory:** Written early in the pre-launch push; some rows still say "Not started" but are done — prefer this handoff + git log.
- **Long git status from UI experiments:** A large web discovery pass may exist as **uncommitted** local changes on some machines; check `git status` before assuming clean tree.
