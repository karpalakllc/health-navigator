# Zdravje360 — Tasks

Phased backlog for the monorepo. Check items off as they are completed. **Do not start domain feature implementation until Phase 3** unless priorities change and docs are updated.

Legend: `[ ]` open · `[x]` done

---

## Phase 0 — Documentation and conventions

**Goal:** Align team and agents on product intent, architecture, and repo rules without shipping domain code.

- [x] Repository scaffold (`apps/api`, `apps/web`, `docs/`, `infra/`, `packages/`)
- [x] Root `README.md`, `PROJECT_BRIEF.md`, `docs/architecture.md`
- [x] Phased `TASKS.md` and `.cursor/rules/project.mdc`
- [x] Root `.gitignore`
- [ ] Review and approve Phase 0 docs (team sign-off)

**Acceptance:** New contributors can read root docs and understand scaffold vs target, direct API pattern, MK-first locale, and what not to build yet.

---

## Phase 1 — Foundation and local parity

**Goal:** Repeatable local environment and minimal API surface for the web app to integrate against. No domain models.

### Repository and environment

- [ ] Expand root `.gitignore` coverage if gaps appear during Phase 1 work
- [ ] Document standard ports and env vars in `README.md` (after agreed locally)
- [ ] Align `apps/api/.env.example` for PostgreSQL and Redis (no domain tables)
- [ ] Add `apps/web` env example for public API base URL

### Local services (no Docker Compose in repo yet)

- [ ] Document how to run PostgreSQL, Redis, and Meilisearch locally (install or host-specific; see architecture doc)
- [ ] Verify Laravel connects to PostgreSQL and Redis
- [ ] Plan Meilisearch index naming convention (document only until indexes exist)

### API baseline

- [ ] API versioning prefix (e.g. `/api/v1`) and health route
- [ ] CORS configuration for Next.js dev and staging origins
- [ ] Agree and document standard API response envelope (success and error payloads)
- [ ] Basic CI: lint/test for `apps/api` and `apps/web` on push

### Web baseline

- [ ] Shared config module for API base URL
- [ ] Proof-of-life: fetch health endpoint from Next.js (server or client)
- [ ] MK locale groundwork (routing or `next-intl` decision — install only when chosen)

### Infra directory

- [x] Add `infra/` README describing future compose/k8s layout (placeholder OK)
- [ ] Docker Compose or IaC — **deferred** until explicitly scheduled; not required for Phase 1 completion

**Acceptance:** Developer can run API + web + Postgres + Redis + Meilisearch locally, hit health check from the browser, and CI passes on scaffold tests.

**Blocked by:** None (start after Phase 0 sign-off).

---

## Phase 2 — Platform (auth, admin, API contract)

**Goal:** Staff can administer content; clients can authenticate; API shape is stable for domain teams.

- [ ] Choose auth model (e.g. Laravel Sanctum SPA/token, session cookies) and document in `docs/architecture.md`
- [ ] User model extensions: roles (admin, moderator, member) — minimal, no forum profile yet
- [ ] Install and configure Filament; restrict to staff roles
- [ ] Admin authentication separate from public member auth
- [ ] Public API authentication endpoints and policies skeleton
- [ ] OpenAPI or equivalent contract published from `apps/api`
- [ ] Meilisearch Laravel integration package (when search epics approach)
- [ ] Queue workers documented for async jobs (indexing, notifications)

**Acceptance:** Admin user can log into Filament; authenticated API client can call protected placeholder route; contract doc exists.

**Blocked by:** Phase 1.

---

## Phase 3 — Domain epics (order tentative)

Implement one epic at a time behind feature flags or env toggles where useful. Each epic needs migrations, API resources, policies, Filament resources where applicable, and Next.js pages — **only when that epic is active**.

| Epic | Scope (high level) | Depends on |
|------|-------------------|------------|
| 3a Doctors | Specialties, profiles, search/list/detail | Phase 2, Meilisearch plan |
| 3b Facilities | Clinics, hospitals, labs, pharmacies | 3a (shared location/geo patterns optional) |
| 3c Pharmacy catalog | Products, prices, pharmacy linkage | 3b |
| 3d Reviews | Submit, pending, approve/reject, display | 3a, 3b |
| 3e Forum | Categories, topics, posts, moderation, sticky, achievements | Phase 2 |
| 3f Triage | Symptom flow, AI integration, disclaimers, logging | Phase 2, legal copy |
| 3g Sponsorships | Featured slots, labeling, admin scheduling | 3a–3c |
| 3h CMS pages | Static/editorial pages, banners | Filament (Phase 2) |

Do not create tickets that implement multiple epics in one PR.

**Blocked by:** Phase 2.

---

## Phase 4 — Hardening and mobile readiness

- [ ] Rate limiting and abuse protection on public API
- [ ] Audit logging for admin and moderation actions
- [ ] Staging environment and deployment runbooks in `infra/`
- [ ] Performance budgets and search relevance tuning
- [ ] Mobile API compatibility review (versioning, pagination, media URLs)

**Blocked by:** Core domain epics substantially complete.

---

## Phase 5 — Mobile apps (future)

- [ ] Native or cross-platform clients against versioned API
- [ ] Push notifications strategy (if required)
- [ ] App store compliance and MK store listings

**Blocked by:** Stable API v1 and auth for mobile clients.

---

## Explicitly deferred

- Next.js BFF layer (optional only for documented SSR/session cases)
- English locale until after MK launch quality bar
- Docker Compose in repo (until infra task is prioritized)
- Domain business logic in Phase 0–1

---

## How to use this file

1. Pick the earliest open phase.
2. Complete acceptance criteria before moving on.
3. Update `docs/architecture.md` when making binding technical decisions.
4. Link PRs to task bullets where helpful.
