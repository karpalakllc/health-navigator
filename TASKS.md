# Zdravje360 — Tasks

Backlog and checklists for the monorepo.

**Canonical planning model:** active **MVP** (product finalization), then roadmap phases **R1–R8** and gated epic **G (3f-b AI)** — see [docs/roadmap.md](./docs/roadmap.md) for the one-page summary.

**Public web — full UI target:** [docs/frontend-ui-transformation.md](./docs/frontend-ui-transformation.md) (shell, all pages, search overlay). Incremental notes: [docs/frontend-ux-plan.md](./docs/frontend-ux-plan.md). **Next-wave discovery & search:** [docs/frontend-discovery-content-plan.md](./docs/frontend-discovery-content-plan.md). **Backend admin & API parity:** [docs/backend-improvement-plan.md](./docs/backend-improvement-plan.md). **Pre-launch full product (owner plan):** [docs/pre-launch-master-plan.md](./docs/pre-launch-master-plan.md).

**Historical labels:** Phases 0–4 and domain IDs **3a–3f-a** remain in the archive below for traceability. New work is tracked under **R*** / **G**, not “Phase 3g/3h”.

Legend: `[ ]` open · `[x]` done

---

## How to use this file

1. **Active work:** [Beta verification](#beta-verification-resume) / deploy when you choose. **Optional / post-beta web discovery:** [Frontend — discovery & content (Track W7)](#frontend--discovery--content-track-w7). Legacy: [Frontend UI transformation](#frontend-ui-transformation-target-experience) (T1–T4 **done**), [Frontend UX](#frontend-ux-active) Track W **done**. MVP product finalization is **complete** — see [MVP — Product finalization](#mvp--product-finalization-complete).
2. **Do not** start broad R2+ search infrastructure (Meilisearch), **G (3f-b AI)**, or **R4** sponsorship campaigns without explicit reprioritization — *unless* a small R1-safe web slice is explicitly scoped in [frontend-discovery-content-plan.md](./docs/frontend-discovery-content-plan.md) (e.g. SQL-only aggregates, no new services).
3. **One epic per PR** where possible; implement MVP chunks **MVP-1 → MVP-5** in order unless noted.
4. Update [docs/architecture.md](./docs/architecture.md) only for binding *technical* decisions (not roadmap prose).
5. **Do not start G (3f-b AI)** without legal sign-off and an updated [docs/triage-safety.md](./docs/triage-safety.md).
6. Link PRs to MVP or roadmap bullets (e.g. `MVP-1`, `R3-C1`).

---

## MVP — Product finalization (complete)

**Goal:** Credible **internal** MVP — staff can load content, moderate UGC, and use the public site daily. **Signed off** via [docs/mvp-acceptance.md](./docs/mvp-acceptance.md). **Not** launch-ready until [beta verification](#beta-verification-resume) passes on staging.

**Out of scope for MVP:** staging/prod deploy, beta verification execution, legal counsel sign-off, Meilisearch, sponsorships, registration, **3f-b AI**, mobile, visual triage rule builder, full visual rebrand.

### Permissions (MVP-1 — decided)

**Chosen:** Moderators **view** directory/guidance/forum categories for context; **mutations** to directory, catalog, triage config, and forum categories require **admin**. UGC moderation (reviews, topics, replies) remains **staff**.

| Area | Admin | Moderator |
|------|-------|-----------|
| Users (create/edit/set password) | Yes | No |
| Reviews / forum topics / replies | Yes | Yes (moderate) |
| Doctors, facilities, specialties, products, triage flows | Full CRUD | View only |
| Forum categories | Full CRUD | View only |

Implemented via `AdminManagesDirectoryRecords` policy trait (MVP-1).

### MVP-1 — Admin moderation & member ops

**Highest priority (before triage admin niceties):**

- [x] Dashboard: pending counts (reviews, forum topics, forum posts)
- [x] Bulk approve / reject (reviews; forum topics; posts)
- [x] Member ops: set password in Filament; invite-only helper on user form
- [x] Table filters: `status=pending` defaults retained; **published / draft** filters on directory + guidance
- [x] Nav groups: Directory · Community · Guidance · Users
- [x] Permissions: moderators view directory; admins mutate directory/triage/categories

### MVP-2 — Web i18n + UI primitives

**Highest priority:**

- [x] MK string sweep (detail pages, UGC forms, account, errors — extend `src/i18n/mk.ts`, no `next-intl`)
- [x] Shared primitives: `BackLink`, `PageSection`, `PageShell`, `ContactBlock`, `LoginPrompt`; `max-w-4xl` layout constant
- [x] Fix theme basics (`globals.css`: Geist font, removed `prefers-color-scheme` variable conflict)

### MVP-3 — Web detail, account & UGC completeness

- [x] Review / forum / account UX polish (stars, pending copy, empty states, form labels from `mk`)
- [x] Doctor detail: affiliated **facilities** on public page (API `facilities[]` + `EntityLinkList`)
- [x] Pharmacy/product/facility detail consistency with shared components

### MVP-4 — Admin content ergonomics

**After MVP-1 priorities:**

- [x] Directory relation editing: searchable attach (doctor↔facility), not huge checkbox lists
- [x] Publish/draft visibility improvements (filters, optional public URL hint)
- [x] Symptom guidance admin: JSON **validation**, helper text, safer dehydrate — **no visual rule builder**
- [x] Pharmacy shelf: practical improvements only (e.g. stale `price_updated_at` visible in table)

### MVP-5 — Internal acceptance pass

- [x] Scripted walkthrough: [docs/mvp-acceptance.md](./docs/mvp-acceptance.md) (directory publish → member review/topic → moderate → verify on web)
- [x] Automated API flow: `apps/api/tests/Feature/MvpAcceptanceFlowTest.php`
- [x] Engineering baseline: API tests + web lint/build green; no P0 code fixes required in this pass
- [x] Sign off MVP → resume [beta verification](#beta-verification-resume)

**MVP acceptance:** Team can run content + moderation internally without engineering for routine tasks. *Human sign-off table in mvp-acceptance.md is for product/ops when they run the walkthrough locally.*

**Active planning focus:** [Beta verification](#beta-verification-resume) / R1 deploy when you choose; optional next wave [Track W7 — discovery & content](#frontend--discovery--content-track-w7) per [frontend-discovery-content-plan.md](./docs/frontend-discovery-content-plan.md).

---

## Frontend UI transformation (target experience)

**Doc:** [docs/frontend-ui-transformation.md](./docs/frontend-ui-transformation.md) — master plan for a **product-grade** public web (header/footer, all pages, **search as overlay** not primary nav). Implements in phases **T1** (shell) → **T4** (auth/forum/guidance polish).

### T1 — Global shell

- [x] Header: remove `/search` from primary nav; add **search icon** → modal/command palette (reuse multi-destination links from `directorySearchHref`; optional keep `/search` route off-nav)
- [x] Header: account **dropdown** (logged in); clear **Најава** CTA (logged out); mobile **sheet** or bottom bar (pick one)
- [x] Footer: multi-column (directory / resources / legal), token-based styling; emergency strip
- [x] Search overlay + `SearchDialogProvider` (custom modal, no new deps); optional full `/search` page remains for bookmarks

### T2 — Home & section templates

- [x] Home: hero + trust strip + refined quick actions + featured blocks
- [x] Standardise section spacing: `PageSection` primitive (title, description, actions); adopt site-wide incrementally

### T3 — Directory pages

- [x] Doctors / facilities / pharmacies / products: shared list grid (`DirectoryCardGrid`), sticky filter bar (responsive field grid), token-aligned header/pagination/empty state
- [x] Detail templates: `DirectoryDetailLayout` for doctor/facility/pharmacy; product detail breadcrumbs aligned with directory IA
- [x] Route `loading.tsx` for directory lists + root loading uses shared skeleton

### T4 — Auth, forum, guidance, system

- [x] Login + account hub: split login layout (desktop trust panel), token forms; account sub-nav + `AccountLayout`; forum topic/category breadcrumbs, `StackedList`, `LoginPrompt` / safety / forms aligned with design tokens
- [x] Guidance: wizard framed in bordered panel on the main page
- [x] Loading routes: login, account, search, guidance, forum (+ category + thread); `error`/`not-found` quick links to home, doctors, guidance
- [x] Pagination: optional `pageParam` (fix `topics_page` on account forum)

### Admin — Track A (parallel)

See [Admin — Track A](#admin--track-a-parallel-after-w1) below (A1–A4).

---

## Frontend UX (active)

**Goal:** Fully working, polished product on **local** — no deploy required. Reference: Lovable `health-navigator-mk-main` (patterns only). Plan: [docs/frontend-ux-plan.md](./docs/frontend-ux-plan.md).

### Web — Track W

- [x] **W0** Profile schema + API + Filament fields (doctors, facilities)
- [x] **D0** Rich demo seed (`RichDemoSeeder`, `database/seeders/data/rich-profiles.php`)
- [x] **W1** Design foundation (tokens, Button/Card/Badge/Skeleton, layout widths) — first pass
- [x] **W2** App chrome (sticky header, mobile nav, guidance in nav, active states) — first pass
- [x] **W3** Home & search hub (hero, quick actions, optional featured API blocks)
- [x] **W4** Directory lists & detail (cards, sticky filters, sidebar, breadcrumbs) — incl. products (cards + compare table)
- [x] **W5** Guidance, forum, account polish
- [x] **W6** loading/error/404, per-page SEO (home, doctors, guidance, search, forum, products; product detail dynamic), a11y pass (light)

### Quick wins

- [x] `/guidance` in header nav
- [x] i18n fixes (facilities name label, products price, guidance emergency CTA)
- [x] Home hero + quick actions + featured doctors
- [x] Doctor list cards + richer doctor detail layout

### Admin — Track A (parallel after W1)

- [x] **A1** Filament branding / primary color aligned with web tokens
- [x] **A2** Directory tables density & badges
- [x] **A3** Moderation queue / preview polish
- [x] **A4** Form sections & helper text consistency

**Out of scope:** deploy, Meilisearch UI, maps SDK, barcode/vitamins from reference, public registration.

**Cross-ref:** Filament slices also listed under [Backend — Track B](#backend--track-b-admin--api) phase **B-P3**.

---

## Backend — Track B (admin & API)

**Plan (source of truth for intent):** [docs/backend-improvement-plan.md](./docs/backend-improvement-plan.md)

**Goal:** Complete directory/catalog admin workflows, API parity with the web (pharmacy maps, reviews, offers), tests and contract docs — **R1-safe** (no Meilisearch, sponsorships, AI triage, new Composer deps without approval).

**Phasing:** B-P0 → B-P1 → B-P2 required for backend completeness; B-P3 (Filament polish) parallel; B-P4 optional; B-P5 hardening.

### B-P0 — Pharmacy admin blockers

- [x] **B0.1** Pharmacy create/edit in Filament (`PharmacyResource`, scoped to `type = pharmacy`)
- [x] **B0.2** Clinical-only fields on `FacilityResource`; shelf RM on pharmacy resource only
- [x] **B0.3** Directory nav: **Facilities** (clinical) + **Pharmacies** (separate resource)
- [x] **B0.4** Demo seed: `eurofarm-skopje` includes coordinates
- [x] **B0.5** API tests for pharmacy routes and review separation

### B-P1 — Pharmacy & catalog depth

- [x] **B1.1** `PharmacyDetailResource`: `latitude`, `longitude` (+ web `PharmacyDetail` type)
- [x] **B1.2** Pharmacy admin: map coordinates + office-hours repeater (`FacilityCommonForm`)
- [x] **B1.3** `ProductResource`: `PharmaciesRelationManager` (inverse offers)
- [x] **B1.4** Shelf ergonomics (`price_updated_at` on both shelf RMs — existing)
- [x] **B1.5** Rich demo pharmacy with coordinates

### B-P2 — API parity & contract

- [x] **B2.1** `GET` + `POST` `/pharmacies/{slug}/reviews`
- [x] **B2.2** Feature tests: pharmacy reviews; `/facilities/.../reviews` clinical-only
- [x] **B2.3** `docs/api-contract.md` — pharmacy detail, reviews, admin notes
- [ ] **B2.4** Optional: `GET /facilities` filters (`has_emergency`, `department`) if web needs

### B-P3 — Filament ergonomics (see also Track A)

- [x] **B3.1** Panel branding: `Zdravje360` name, primary **Blue** (matches web), collapsible sidebar
- [x] **B3.2** Directory tables: publication badges, facility type/emergency, offer counts, shared columns
- [x] **B3.3** Moderation: status colors, body excerpts, pharmacy review labels, dashboard deep-links to pending filter
- [x] **B3.4** Taxonomy/specialty forms: fieldsets + helper text; shared `TaxonomyForm` / `TaxonomyTable`
- [x] **B3.5** Taxonomy slug/name helper text (duplicate guardrails remain manual in admin)

### B-P4 — Optional R1 discovery API (defer unless requested)

- [x] **B4.1** `GET /api/v1/search` aggregate (capped per vertical); web unified search uses it
- [x] **B4.2** `GET /specialties/{slug}`
- [x] **B4.3** Facility list filters for emergency / department; web filter bar wired
- [x] **B4.4** `GET /departments` (published slug + name)

### B-P5 — Hardening

- [x] **B5.1** Feature tests: search, departments, specialty show, facility filters
- [x] **B5.2** Policy test: pharmacy `Facility` rows use `FacilityPolicy`
- [ ] **B5.3** Triage session purge — **R2** (D7), not B unless reprioritized
- [ ] **B5.4** Redis rate limits — **R2** (D2)

**Recently shipped (do not re-open):** Doctor profile taxonomies + office-hours repeater; clinical facility departments / emergency / coordinates; Latin/Cyrillic list `q` search.

---

## Frontend — discovery & content (Track W7)

**Plan (source of truth for intent):** [docs/frontend-discovery-content-plan.md](./docs/frontend-discovery-content-plan.md)

**Goal:** Richer **home** content, **honest** ratings/specialty discovery, **unified primary search** + **advanced** modal, **modern directory filters** (doctors first). Align copy with medical trust and sponsored-placement rules.

**Phasing:** See plan §7. Prefer small PRs: `W7-P0` (web-only/static), `W7-P1` (API aggregates), `W7-P2` (search IA), `W7-P3` (filter shell).

### W7-P0 — Home & trust (minimal API)

- [x] **H1** “Како функционира” (3–4 steps, MK, non-diagnostic) on `app/page.tsx`
- [x] **H2** “Болници и клиники” teaser — links into `/facilities` with type-appropriate query/hash if supported by existing filters
- [x] **H3** Optional forum/guidance teaser row (static CTA if no API slice)
- [ ] Copy review for **homepage** blocks (sponsored vs organic separation)

### W7-P1 — API-backed discovery

- [x] **S1** `GET /specialties` (or resource) includes **published doctor count** for specialty explorer cards
- [x] **S2** `GET /doctors` supports **sort** (e.g. rating) + **min_reviews** threshold for “top on platform” home section
- [x] **S3** Tests + `docs/api-contract.md` update for new query fields

### W7-P2 — Search IA

- [x] **Q1** Unified results route: single `q` (+ optional `city`) shows grouped matches across directories (parallel fetches **or** new aggregate endpoint)
- [x] **Q2** Header / hero: primary search uses unified flow; **advanced** opens modal (`SearchDialogProvider`) — label e.g. “Напредно пребарување”
- [x] **Q3** ⌘/Ctrl+K behavior documented and implemented consistently
- [x] **Q4** `/search` page refactored to match; remove duplicate/conflicting UX

### W7-P3 — Directory filter UI

- [x] **F1** `/doctors` filter/search **toolbar** + mobile UX (sheet/drawer pattern, no new deps unless approved)
- [x] **F2** Roll shared shell to facilities / pharmacies / products lists
- [x] **F3** Empty states + `aria-live` result counts

**Note:** T1 originally shipped “search icon → **modal**”; W7-P2 **replaces** primary search behavior per product sign-off — update handoff when shipped.

---

## Beta checklist

Use this list to declare **beta-ready**. All **required** items must be checked unless marked *invite-only alternative*.

**R1 defaults (locked):** Path B invite-only · static legal pages in `apps/web` · PaaS deploy runbook · Sentry · MK dictionary (no `next-intl`) · cookies in privacy only.

### Product & content

- [x] **H1** — Macedonian-first public UI chrome (`src/i18n/mk.ts`); DB content may stay EN — see content debt below.
- [x] **E1 + A5** — Static `/privacy`, `/terms`, `/disclaimer` (legal review still required).
- [x] **Onboarding path** — **Path B:** [docs/beta-closed.md](./docs/beta-closed.md); no public sign-up.
- [x] **A2** — **N/A** (invite-only; admin resets password in Filament).
- [ ] Real directory content loaded (doctors, facilities, pharmacies) — *ops/content, not code*.
- [ ] Moderation process for reviews and forum — *ops*.

### Engineering & ops

- [x] **D1** — Repo: [infra/deploy.md](./infra/deploy.md), env examples, CORS env, seed safety docs. *Hosting apply is ops.*
- [x] **D4 (light)** — Sentry wired (API + web); set DSNs per environment. *Uptime monitoring is ops.*
- [ ] CI green on `main`; seeded credentials rotated for non-local environments.
- [ ] [docs/triage-safety.md](./docs/triage-safety.md) constraints respected in production copy for Symptom guidance.

### Explicitly out of beta scope

Do **not** block beta on: Meilisearch (R3), sponsorships (R4), **3f-b AI triage (G)**, mobile (R8), checkout, deep forum/pharmacy/directory expansions (R6), English locale, OpenAPI export.

---

## Beta scope (summary)

| In beta | Out of beta |
|---------|-------------|
| Shipped platform + domains (see [Completed foundations](#completed-foundations)) | Unified Meilisearch search |
| R1 blockers only | Sponsored placements |
| Rule-based Symptom guidance (3f-a) | AI-assisted triage (3f-b) |
| SQL `/search` hub + list filters | Native mobile apps |
| Invite-only **or** registration + reset | Checkout, pharmacy integrations, rich media/maps |

**Registration policy:** Public registration (**A1**) is **preferred** before a **broad** public beta. It is **not mandatory** for a **closed, invite-only** beta — document the chosen path in the checklist above and in release notes.

---

## Beta verification (resume)

> **Unparked** after **MVP-5**. Use for staging/external invite-only beta — not for new MVP feature work.

**Checklist:** [docs/beta-verification.md](./docs/beta-verification.md) — practical pass before **external** invite-only beta.

**Internal MVP walkthrough (done):** [docs/mvp-acceptance.md](./docs/mvp-acceptance.md)

| Track | Owner | Status |
|-------|-------|--------|
| Repo (tests, build, no register CTA) | Engineering | [ ] |
| Staging deploy + end-to-end smoke | Eng + ops | [ ] |
| Legal copy approved (no DRAFT for external) | Legal → eng PR | [ ] |
| Content + moderation + tester provisioning | Ops / content | [ ] |
| Sentry + health checks | Ops | [ ] |

**DRAFT legal pages:** OK for staging/internal review only — **not** for external testers.

**Policy:** [docs/beta-closed.md](./docs/beta-closed.md) · **Content/moderation:** [docs/beta-content-readiness.md](./docs/beta-content-readiness.md) · **Deploy:** [infra/deploy.md](./infra/deploy.md)

---

## R1 — Beta launch blockers (complete — repo, launch parked)

**Goal:** Credible closed invite-only beta. **Repo deliverables done.** Ops/deploy, legal external sign-off, and [beta verification](#beta-verification-resume) are **active** after MVP sign-off.

| ID | Epic | Status |
|----|------|--------|
| D1 | Deploy / staging / prod baseline | [x] repo docs; [ ] ops deploy |
| H1 | Macedonian-first UI (lightweight dictionary) | [x] |
| E1 | Legal / editorial essentials (static web) | [x] |
| A5 | Policy & disclaimer pages | [x] |
| A1 | Path B invite-only documented | [x] |
| A2 | Password reset | [x] N/A |
| D4 | Sentry (errors only) | [x] |

### D1 — Deploy / staging / prod baseline

- [ ] Staging environment parity with production topology
- [ ] Production: managed PostgreSQL, TLS, secrets via env (not committed)
- [ ] Separate deployables for `apps/api` and `apps/web`
- [ ] Database backup / restore documented
- [ ] `infra/` assets or runbook (compose, IaC, or host-specific — team choice)

### H1 — Macedonian-first public UI

- [ ] Choose and wire i18n (`next-intl` per architecture)
- [ ] MK strings for nav, directories, forum, guidance, auth, errors, legal pages
- [ ] `layout` / `lang` attributes correct for MK
- [ ] English remains deferred (post-beta)

### E1 + A5 — Legal / editorial essentials

- [ ] Minimal CMS or static pipeline for legal/editorial pages (slug, title, body, publish)
- [ ] Web routes for legal pages (e.g. `/privacy`, `/terms`, `/disclaimer`)
- [ ] Footer / header links to legal pages
- [ ] Staff can update copy in Filament or agreed static workflow

### A1 — Registration **or** invite-only onboarding

**Pick one path and document it in the beta checklist.**

**Path A — Public registration (preferred before broad beta)**

- [ ] `POST /auth/register` (or equivalent) + validation
- [ ] Terms acceptance at sign-up
- [ ] Web sign-up flow; member role assignment
- [ ] Rate limits / abuse controls

**Path B — Invite-only beta (*valid without A1*)**

- [ ] Written beta scope: “members are staff-provisioned only”
- [ ] Filament or documented process to create member accounts
- [ ] No public sign-up link in UI
- [ ] Communicate invite-only limitation to testers

### A2 — Password reset

- [ ] Required when **Path A** is chosen
- [ ] Forgot-password API + web flow (email driver configured for staging/prod)
- [ ] For **Path B**, mark N/A in beta checklist

### D4 — Light observability

- [ ] Error tracking hooked to API and web (e.g. Sentry)
- [ ] Alerting or dashboard for `GET /api/v1/health` (and web availability)
- [ ] Structured logging baseline documented

**R1 acceptance:** Beta checklist (required items) can be signed off for the chosen onboarding path.

---

## R2 — Post-beta scale

**Goal:** Operate reliably as traffic and UGC grow.

- [ ] **D2** — Redis wired (cache, rate limits, sessions/queues per architecture)
- [ ] **D3** — Queue workers documented and running in staging/prod
- [ ] **D7** — Triage session purge job (90-day default per [triage-safety.md](./docs/triage-safety.md))
- [ ] **D8** — Moderation and incident runbooks
- [ ] Redis-backed rate limiting (replace file/database where needed)
- [ ] Security headers / CSP on Next.js (architecture security baseline)

---

## R3 — Search infrastructure

**Goal:** Meilisearch-backed discovery; not required for beta.

- [ ] **C1** — Meilisearch Laravel integration + env config
- [ ] **C2** — Index jobs: `doctors`, `facilities`, `pharmacy_products`, `forum_topics`
- [ ] **C3** — Unified search API + web hub (replace or augment thin `/search`)
- [ ] **C4** — SQL fallbacks retained; optional DB indexes if profiling warrants
- [ ] Local Redis + Meilisearch documented in README

**Depends on:** R2 (D2/D3) recommended first.

---

## R4 — Monetization (sponsorships)

**Goal:** Transparent paid visibility — post-beta unless sales requires earlier pilot.

- [ ] **B1** — Sponsored flag on doctors / facilities / products in API + UI labels
- [ ] **B2** — Filament: campaigns, schedules, placement slots
- [ ] Copy review: paid vs organic clearly distinguished (see PROJECT_BRIEF)
- [ ] No checkout / billing in this phase

**Out of scope:** Self-serve billing, auctions.

---

## R5 — Editorial CMS (beyond legal)

**Goal:** Marketing and editorial content after legal essentials (R1 E1) exist.

- [ ] **E2** — Pages, banners, optional EN fields later
- [ ] **E3** — Public `/pages/[slug]` (or equivalent) for non-legal content
- [ ] Filament workflows for editors

---

## R6 — Domain depth (optional, pick per priority)

Not beta blockers. Implement as small epics; do not bundle.

### Forum

- [ ] Member edit/delete own pending content
- [ ] Reports / flags queue
- [ ] Nested replies, attachments (if ever)

### Pharmacy & catalog

- [ ] Product images, price history
- [ ] `/pharmacies/{slug}/reviews` alias (optional)
- [ ] External stock/price APIs — only if product approves

### Directories

- [ ] Photos, opening hours, maps/geo
- [ ] Doctor detail: affiliated facilities
- [ ] `GET /specialties/{slug}`

### Reviews & guidance

- [ ] Review author edit window, reporting
- [ ] Guidance: session resume API (if product wants)
- [ ] Guidance: richer handoffs (still non-diagnostic)

**Explicitly out:** Checkout, e-commerce cart, appointment booking.

---

## G — 3f-b AI triage assist (gated — not beta)

**Do not implement in R1 or beta.** Separate epic from rule-based Symptom guidance (3f-a).

**Gates (all required before code):**

- [ ] Legal sign-off for AI-specific claims and retention
- [ ] [docs/triage-safety.md](./docs/triage-safety.md) updated (AI scope, fail-closed, banned claims)
- [ ] Product decision recorded in TASKS / roadmap
- [ ] Feature flag / env toggle designed

**Implementation (after gates):**

- [ ] Provider abstraction in API; audit log for prompts/responses
- [ ] Fail-closed MK fallback copy
- [ ] No diagnostic certainty language in UI or API outcomes
- [ ] Web: AI clearly optional sub-step; disclaimers on every AI touchpoint

---

## R8 — Mobile apps (later)

- [ ] Client against stable `/api/v1` + auth story (A1/A2)
- [ ] Push notifications strategy (if required)
- [ ] App store compliance and MK listings

**Blocked by:** R1 auth path stable; API contract stable.

---

## Completed foundations

Everything below is **shipped** unless noted. API details: [docs/api-contract.md](./docs/api-contract.md).

| Area | Status |
|------|--------|
| Phase 0 — Docs & conventions | ✅ |
| Phase 1 — Local API/web/Postgres/CI | ✅ (Redis/Meilisearch local optional) |
| Phase 2 — Auth, Filament, API contract | ✅ (OpenAPI export deferred) |
| 3a Doctors directory | ✅ |
| 3b Clinical facilities | ✅ |
| 3c Reviews + moderation | ✅ |
| 3d Pharmacy catalog | ✅ |
| 3e Forum + moderation | ✅ |
| 3f-a Symptom guidance (rules, no AI) | ✅ |
| Phase 4 — Public UX hardening | ✅ |

**Not shipped:** Registration, password reset, MK i18n, Meilisearch, Redis in prod, sponsorships, CMS (beyond what R1 adds), 3f-b AI, mobile, staging/prod (R1 D1).

---

## Historical archive (Phases 0–4 & 3a–3f-a)

<details>
<summary>Phase 0 — Documentation (complete)</summary>

- [x] Repository scaffold, README, PROJECT_BRIEF, architecture, TASKS, cursor rules
- [ ] Team sign-off on Phase 0 docs (optional)

</details>

<details>
<summary>Phase 1 — Foundation (complete)</summary>

- [x] API v1, envelope, CORS, health, CI, web API client, Postgres docs
- [ ] Redis/Meilisearch local verify (deferred to R2/R3)
- [ ] Docker Compose in repo (deferred to R1 D1)

</details>

<details>
<summary>Phase 2 — Platform (complete)</summary>

- [x] Sanctum, roles, Filament, `/auth/login`, contract doc
- [ ] OpenAPI export (optional, post-beta)
- [ ] Queue worker docs (R2 D3)

</details>

<details>
<summary>Phase 3a — Doctors (complete)</summary>

- [x] Specialties, doctors, API, Filament, web `/doctors`, tests, seeder

</details>

<details>
<summary>Phase 3b — Facilities (complete)</summary>

- [x] Clinical facilities, API, Filament, web `/facilities`, tests, seeder

</details>

<details>
<summary>Phase 3c — Reviews (complete)</summary>

- [x] Polymorphic reviews, moderation, member submit, web forms, tests

</details>

<details>
<summary>Phase 3d — Pharmacy (complete)</summary>

- [x] Pharmacies, products, shelf prices, Filament, web, tests

</details>

<details>
<summary>Phase 3e — Forum (complete)</summary>

- [x] Categories, topics, posts, moderation, rate limits, web, cookie bridge, tests

</details>

<details>
<summary>Phase 3f-a — Symptom guidance (complete)</summary>

- [x] Rule-based flow, triage API, Filament, web `/guidance`, [triage-safety.md](./docs/triage-safety.md), tests

</details>

<details>
<summary>Phase 4 — Hardening & public UX (complete)</summary>

- [x] Rate limits, JSON errors, token expiry, site shell, login/account, review submit, `/search` hub
- [x] Cookie bridge for reviews + forum
- [ ] Meilisearch, registration (moved to R1/R3)

</details>

---

## Explicitly deferred (cross-cutting)

- Next.js BFF beyond documented cookie bridge exceptions
- English locale until after MK launch quality bar (R1 H1 first)
- Checkout / pharmacy e-commerce
- Pharmacy external integrations without product approval
- OpenAPI export until team wants SDK/docs portal

---

## Open product decisions (sign-off)

| Decision | Options | Default in docs |
|----------|---------|-----------------|
| Beta onboarding | **A1** public registration vs **invite-only** | Invite-only allowed; registration preferred before broad beta |
| CMS for R1 legal pages | Minimal Filament pages vs static MD/MDX in web | Team choice in R1 E1 |
| Hosting / IaC | Compose vs managed PaaS vs k8s | Team choice in R1 D1 |
| Error tracking vendor | Sentry vs other | Team choice in R1 D4 |

Record the chosen option in the beta checklist when decided.
