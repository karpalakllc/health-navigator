# Pre-launch master plan — full product before deploy

**Status:** Active planning (supersedes “R2/R3 only after beta” for **your** timeline).  
**Audience:** Product owner + engineering.  
**Last updated:** 2026-05-16 (P0 decisions locked)

**Related (existing):** [roadmap.md](./roadmap.md) · [TASKS.md](../TASKS.md) · [backend-improvement-plan.md](./backend-improvement-plan.md) · [frontend-ux-plan.md](./frontend-ux-plan.md) · [frontend-discovery-content-plan.md](./frontend-discovery-content-plan.md) · [beta-verification.md](./beta-verification.md)

**Obsolete for planning:** [beta-closed.md](./beta-closed.md) (invite-only beta) — superseded by open registration + admin settings.

---

## 1. What you want (in plain terms)

Before the public site goes live, you want:

| Goal | Meaning |
|------|---------|
| **Complete core modules** | Doctors and facilities (and their admin) feel finished end-to-end — not “MVP skeleton.” |
| **Forum that people actually use** | Much better browsing, writing, and moderation experience than today. |
| **Intentionally hidden areas** | Symptom guidance (`/guidance`), **products**, and **pharmacies** (catalog was built for future price comparison) show **“coming soon”** with blur — not usable at launch. Product data later via **scraper API** (separate future epic). |
| **Accounts** | Public **registration** + **login**, controlled from **admin settings** (e.g. turn registrations on/off) without code deploys. |
| **Emails** | Sign-in, password reset, review lifecycle, doctor/facility notifications — detailed later; infrastructure must be planned early. |
| **Operations** | Dashboard in admin, **analytics** (see §8), **permissions** that match how you run the team. |
| **Infrastructure** | Everything we previously parked in **R1 + R2 + R3** — deploy-ready, scalable search, background jobs — done **before** launch, not after. |

This document turns that into **phases**, maps **what is already done**, and lists **recommended order**.

---

## 2. Honest inventory — what exists today

Legend: **Done** · **Partial** · **Not started**

### 2.1 Platform & launch (was R1)

| Item | Status | Notes |
|------|--------|--------|
| API + web apps working locally | **Done** | Laravel API, Next.js, Filament `/admin` |
| Macedonian UI dictionary (`mk.ts`) | **Done** | No full `next-intl` |
| Legal pages (privacy, terms, disclaimer) | **Done** | Static in web; legal **review** still ops |
| Invite-only beta docs | **Obsolete** | Ignore; launch uses **open registration** + settings |
| Sentry (errors) | **Done** | Needs DSNs per environment |
| Staging/production deploy | **Not started** | [infra/deploy.md](../infra/deploy.md) — ops |
| Beta verification checklist executed | **Not started** | [beta-verification.md](./beta-verification.md) |
| CI green on `main` | **Partial** | Exists; keep green through phases |

### 2.2 Infrastructure (was R2)

| Item | Status | Notes |
|------|--------|--------|
| PostgreSQL | **Done** | Production target |
| Redis | **Not started** | Planned for cache, queues, rate limits |
| Queue workers | **Not started** | Needed for email, search indexing, cleanup jobs |
| Triage session auto-delete (90 days) | **Not started** | [triage-safety.md](./triage-safety.md) |
| Redis-backed rate limits | **Not started** | File/DB limits exist for some routes |
| Moderation/incident runbooks | **Not started** | Docs only |
| Security headers / CSP (web) | **Not started** | Architecture baseline |

### 2.3 Search (was R3)

| Item | Status | Notes |
|------|--------|--------|
| SQL search on each list (`q`, Latin/Cyrillic) | **Done** | Doctors, facilities, forum topics (+ pharmacies/products in code; **hidden** at launch) |
| Unified `GET /search` + web one-call results | **Done** | Recent Track B-P4 |
| Meilisearch + index sync jobs | **Not started** | True “fast/smart” search at scale |
| Forum in global search | **Not started** | Optional R3 extension |

### 2.4 Backend admin (Filament)

| Item | Status | Notes |
|------|--------|--------|
| Doctors CRUD + taxonomies + office hours | **Done** | Recent admin depth |
| Clinical facilities CRUD + departments, emergency, map | **Done** | |
| Pharmacies (separate resource) + shelf | **Done** | Admin only at launch; **public hidden** |
| Products + offers from product side | **Done** | Admin only at launch; **public hidden**; scraper fill later |
| Reviews / forum moderation queue | **Partial** | Works; polish done in B-P3 |
| Users (create, roles, set password) | **Done** | No self-registration |
| Symptom guidance flow admin | **Done** | JSON rules; hide public later |
| **Site settings** (registrations on/off, feature flags) | **Not started** | You requested — needs new model/UI |
| **Analytics dashboard** | **Not started** | Need frontend traffic + backend operational metrics (§8) |
| **RBAC** (create roles, assign permissions) | **Not started** | Today: fixed `admin` / `moderator` / `member` enum only |
| **Staff vs client users in admin** | **Not started** | Single Users list today; members mixed with staff |

### 2.5 Doctors module (public + API)

| Item | Status | Notes |
|------|--------|--------|
| List + filters + sort/rating | **Done** | W7 + API |
| Detail: hero, taxonomies, facilities, hours, reviews | **Done** | Polished in recent web work |
| Submit review (member) | **Done** | Moderated |
| Admin maintenance | **Done** | |
| **“Fully working” gaps** | **Partial** | See §4.1 — e.g. doctor-facing notifications, claim profile, sponsored labels (R4) |

### 2.6 Facilities module (public + API)

| Item | Status | Notes |
|------|--------|--------|
| List + filters (type, city, q, emergency, department) | **Done** | Recent B-P4 + web filter bar |
| Detail: departments, emergency, map, doctors, reviews | **Done** | |
| Pharmacy split (separate routes/admin) | **Done** | |
| **“Fully working” gaps** | **Partial** | See §4.2 |

### 2.7 Products & pharmacies (hidden at launch)

| Item | Status | Notes |
|------|--------|--------|
| API + admin catalog (manual CRUD) | **Done** | Keep for staff; not public |
| Public list/detail/compare | **Done in code** | **Launch:** coming soon + blur |
| Pharmacies list/detail | **Done in code** | **Launch:** coming soon + blur (same story as products) |
| Scraper API product ingest | **Not started** | **Future epic** — do not block launch |

### 2.8 Symptom guidance (you want hidden)

| Item | Status | Notes |
|------|--------|--------|
| Rule-based flow API + admin | **Done** | 3f-a |
| Public `/guidance` wizard | **Done** | You want **coming soon** + blur |
| AI triage (G) | **Not started** | Gated; out of pre-launch unless reprioritized |

### 2.9 Forum (you want massive improvements)

| Item | Status | Notes |
|------|--------|--------|
| Categories, topics, replies API | **Done** | Moderated |
| Public: category list, topic list, thread, create topic/reply | **Partial** | Functional but **W5 forum UI items still open** in [frontend-ux-plan.md](./frontend-ux-plan.md) |
| Search topics in category | **Done** | API |
| Reports / flags, edit own post, nested replies | **Not started** | R6 optional items |
| Rich editor, attachments, gamification | **Not started** | PROJECT_BRIEF long-term |

### 2.10 Auth, registration, settings

| Item | Status | Notes |
|------|--------|--------|
| Login (API + web) | **Done** | Cookie bridge |
| Member reviews + forum | **Done** | |
| **Public registration** | **Not started** | Was explicitly out of beta |
| **Password reset** | **Not started** | |
| **“Enable registrations” in admin** | **Not started** | |
| **Transactional email** | **Not started** | Mail config exists; no product emails |

### 2.11 Emails (later detail — plan slots now)

| Event | Status |
|-------|--------|
| Welcome / verify email on register | **Not started** |
| Password reset | **Not started** |
| Review submitted (user confirmation) | **Not started** |
| Review approved / rejected (author) | **Not started** |
| New review pending (staff digest) | **Not started** |
| Notify doctor/facility when reviewed | **Not started** | Needs product rules (email to whom?) |
| Forum reply approved | **Not started** |

---

## 3. Recommended phase model (before deploy)

Work in **vertical slices** (one PR theme) but follow this **order** — later phases depend on earlier ones.

```text
P0  Decisions ✅ LOCKED (below)
P1  Launch infrastructure (R1 + R2)
P2  Search at scale (R3) — doctors, facilities, forum only at launch
P3  Platform settings + auth (registration, flags)
P4  Doctors & facilities “complete”
P5  Forum overhaul
P6  Coming soon: guidance + products + pharmacies (blur)
P7a Analytics dashboard (frontend + backend)
P7b RBAC + staff/client split in admin
P7c Forum-scoped roles (after P5)
P8  Email waves
P9  Final QA + deploy
——— future ———
PF  Product scraper API + public catalog enablement
```

### P0 — Decisions ✅ LOCKED

| Topic | Decision |
|-------|----------|
| **Beta closed / invite-only** | **Not used.** Open registration with admin toggle. |
| **Products at launch** | **No public catalog.** Admin can keep manual product/pharmacy records if useful internally. |
| **Pharmacies at launch** | **No public pharmacies** — same as products; section was for future price comparison. Public routes show **coming soon** + blur. |
| **Product data later** | **Scraper API** ingestion is a **separate future epic** (PF); do not block pre-launch. |
| **Guidance at launch** | **Coming soon** + blur on `/guidance`; no live triage for users. |
| **Analytics** | **Both:** (1) **Frontend** traffic analytics on the public site, (2) **Backend** operational metrics in a **well-structured Filament dashboard** (not one cluttered page). |
| **Permissions** | **Full RBAC:** create **roles**, assign **permissions** to roles, permissions cover **all admin areas**. |
| **Users in admin** | **Two groups:** **Staff members** (Filament operators) vs **Client members** (registered public users) — **separate lists**, not one mixed table. |
| **Community moderators** | **Client members** can be granted forum powers **after forum is done:** e.g. full forum moderator, or **per forum category/section** — implemented via roles + permissions, not a separate app. |

**Launch product surface (public):** doctors, facilities, forum, search, accounts — plus legal/static pages.

---

### P1 — Launch infrastructure (R1 + R2 merged)

**Goal:** Staging and production behave like a real service; background work is reliable.

| ID | Work |
|----|------|
| P1.1 | Staging environment (API + web + DB + secrets) per [infra/deploy.md](../infra/deploy.md) |
| P1.2 | Redis + queue workers (staging + prod) |
| P1.3 | Move rate limits to Redis where needed |
| P1.4 | Scheduled job: purge old triage sessions (90d) |
| P1.5 | Web security headers / CSP baseline |
| P1.6 | Backups, health checks, Sentry in staging/prod |
| P1.7 | Ops runbooks (moderation, incident, deploy rollback) |

**Exit:** Staging URL passes [beta-verification.md](./beta-verification.md) technical items.

---

### P2 — Search at scale (R3)

**Goal:** Search stays fast and relevant as content grows.

| ID | Work |
|----|------|
| P2.1 | Meilisearch in dev/staging/prod (or approved alternative) |
| P2.2 | Index jobs at launch: **doctors**, **clinical facilities**, **forum topics** — skip products/pharmacies until PF enables public catalog |
| P2.3 | Wire public search to indexes; keep SQL fallback if index down |
| P2.4 | Reindex on publish/unpublish in admin |

**Exit:** `/search` and header search feel instant with realistic seed volume.

**Note:** You already have SQL unified search; P2 **replaces the engine**, not the UX.

---

### P3 — Platform settings + authentication

**Goal:** Control behavior from admin; users can register without staff creating accounts.

| ID | Work |
|----|------|
| P3.2 | Filament **Settings** page (admin-only) |
| P3.3 | API: `POST /auth/register`, validation, terms acceptance |
| P3.4 | Web: `/register` page; hide CTA when `registrations_enabled = false` |
| P3.5 | Password reset API + web |
| P3.6 | API reads flags → 404 or “coming soon” payload for disabled modules |

**Exit:** Toggle registrations in admin; no code change to disable sign-up.

---

### P4 — Doctors & facilities “complete”

**Goal:** No known gaps for launch scope in the two core directory modules.

**Doctors — verify/fix:**

| ID | Work |
|----|------|
| P4.1 | List/detail/admin parity audit (every admin field has public effect or is marked internal) |
| P4.2 | Review submit + display edge cases (unpublished, duplicate, moderation states) |
| P4.3 | Optional: `GET /doctors/{slug}/…` only if needed for launch (e.g. related content) |
| P4.4 | SEO metadata, 404, empty states, mobile filter UX |
| P4.5 | Content: real MK profiles in staging |

**Facilities — verify/fix:**

| ID | Work |
|----|------|
| P4.6 | Same audit as doctors |
| P4.7 | Map embed when no coordinates (fallback link only) |
| P4.8 | Emergency + department filters tested with real data |
| P4.9 | Doctor↔facility links consistent on both profile types |

**Exit:** Product walkthrough: “I can find any doctor/facility we published, trust the info, and leave a review.”

---

### P5 — Forum overhaul

**Goal:** Forum matches directory quality; supports community at launch.

**Prioritized forum scope (suggest MVP-for-launch vs phase 5b):**

| Priority | Feature |
|----------|---------|
| **P5a (launch)** | Category grid polish; topic list (status, replies, last activity); thread readability; safety banner; mobile; pending-state copy; account “my topics/posts” |
| **P5b (soon after)** | Report/flag queue; edit/delete own pending; better search across forum |
| **P5c (later)** | Nested replies, attachments, achievements |

| ID | Work |
|----|------|
| P5.1 | Close remaining **W5 forum** items in [frontend-ux-plan.md](./frontend-ux-plan.md) |
| P5.2 | API gaps if any (pagination, sorting pinned topics — largely done) |
| P5.3 | Moderation: staff workflow tested under load |
| P5.4 | Anti-abuse: rate limits + clear MK rules in UI |

**Exit:** You are comfortable inviting users to discuss health topics without embarrassment.

---

### P6 — Coming soon: guidance, products & pharmacies

**Goal:** Visitors see honest “наскоро” for unfinished modules; no API leakage.

| ID | Work |
|----|------|
| P6.1 | Shared **ComingSoon** component: blurred preview + MK copy (products section explained as future price comparison) |
| P6.2 | `/guidance` gated (`public_guidance = false`) |
| P6.3 | `/products`, `/products/[slug]`, home product rails → coming soon |
| P6.4 | `/pharmacies`, `/pharmacies/[slug]` → coming soon |
| P6.5 | Unified search / nav: **exclude** products & pharmacies from results while disabled (doctors, facilities, forum only) |
| P6.6 | Admin: triage flow + product/pharmacy CRUD remain for staff preparation |

**Exit:** No triage session, no product/pharmacy browsing on production.

---

### P7a — Analytics dashboard (frontend + backend)

**Goal:** One place in admin to understand **how the site is used** and **how the platform is performing**.

| Layer | What you get | Implementation direction |
|-------|----------------|---------------------------|
| **Frontend (traffic)** | Page views, top pages, referrers, devices — privacy-conscious | e.g. Plausible or Fathom embedded in `apps/web`; MK-friendly; cookie/consent aligned with privacy page |
| **Backend (operational)** | Registrations, active users, reviews submitted/approved, forum activity, directory growth, search queries (aggregated) | Events table or daily rollups + Filament **Analytics** section with charts |
| **Structure** | Not a single wall of numbers | Filament: **Overview** → **Traffic** (embed or API) → **Users** → **Directory** → **Community** → **Search** (if logged) |

| ID | Work |
|----|------|
| P7a.1 | Define event schema (page_view server-side optional; key business events: register, review, topic, login) |
| P7a.2 | Filament analytics pages + date range filters |
| P7a.3 | Integrate frontend analytics provider; document in privacy policy |
| P7a.4 | Dashboard home widgets link into analytics section |

**Exit:** You can answer “how many people signed up this week” and “which pages are visited most” without SQL.

---

### P7b — RBAC + staff / client split

**Goal:** Flexible roles; clear separation of **staff** vs **public users** in admin.

**Target model (conceptual):**

```text
User
 ├── user_kind: staff | client
 ├── Staff → Filament (if role has admin permissions)
 └── Client → public site only (unless given community moderator permissions)

Role (many, you create)
 └── Permissions (granular: doctors.view, doctors.update, forum.moderate, …)

Community moderator (client + role)
 └── Optional: limit to forum category slugs (pivot or permission scope)
```

| ID | Work |
|----|------|
| P7b.1 | Adopt **RBAC package** (recommend `spatie/laravel-permission` + Filament UI for roles/permissions) — **requires dependency approval** |
| P7b.2 | Map existing resources to permission names (directory, community, guidance, users, settings, analytics, roles) |
| P7b.3 | Migrate `admin` / `moderator` to default roles; deprecate hard-coded policy trait over time |
| P7b.4 | Filament: **Staff** resource (users where `user_kind = staff`) |
| P7b.5 | Filament: **Clients** resource (users where `user_kind = client`) — read-only profile, reviews/forum history, assign roles |
| P7b.6 | Filament: **Roles & permissions** CRUD (super-admin only) |
| P7b.7 | `canAccessPanel`: staff with any admin permission OR community mod role with forum permissions |

**Exit:** You can create a role “Forum moderator — Кардиологија” and assign it to a client user without making them full admin.

---

### P7c — Forum-scoped community roles (after P5)

**Goal:** Community moderation without directory access.

| ID | Work |
|----|------|
| P7c.1 | Permissions: `forum.moderate`, `forum.moderate.category.{slug}` (or category ID scoped) |
| P7c.2 | API + Filament: community mods only see/act on allowed categories |
| P7c.3 | Optional Filament panel slice: “Community moderation” for non-staff mods (narrow nav) |
| P7c.4 | Docs: how to onboard a volunteer moderator |

**Depends on:** P5 forum UX complete.

---

### P8 — Email program (phased)

**Goal:** Mail infrastructure ready; templates added incrementally.

| Wave | Scope |
|------|--------|
| **P8.1** | Mail driver prod-ready (SMTP/Postmark/etc.); queue-backed sending; branded templates base |
| **P8.2** | Auth: verify email (if enabled), reset password, welcome |
| **P8.3** | Reviews: submitted, approved, rejected |
| **P8.4** | Staff digests: daily pending moderation (optional) |
| **P8.5** | Directory: notify facility/doctor contact email on new review — **rules workshop** |

**Exit per wave:** Each email tested in staging with mail trap.

---

### P9 — Final gate & deploy

| ID | Work |
|----|------|
| P9.1 | Full regression: API tests + web build + manual launch script |
| P9.2 | [beta-verification.md](./beta-verification.md) + content readiness |
| P9.3 | Legal sign-off on MK copy |
| P9.4 | Production deploy; rotate secrets; monitor Sentry |
| P9.5 | Post-launch: enable registrations when ready |

---

## 4. Module checklists (launch definition of “done”)

### 4.1 Doctors — “fully working”

- [ ] Published doctor appears in list, search, specialty explorer, home sections (if enabled)
- [ ] Detail shows accurate specialties, facilities, hours, contact, reviews
- [ ] Member can submit one review; sees pending state in account
- [ ] Staff can moderate; approved review appears on site
- [ ] Admin can create/edit without developer
- [ ] Featured/sponsored rules clear if R4 deferred (no misleading “top” labels)

### 4.2 Facilities — “fully working”

- [ ] Same as doctors for clinical facilities
- [ ] Map works when lat/lng set; graceful fallback when not
- [ ] Emergency badge and department list accurate
- [ ] Affiliated doctors link correctly

### 4.3 Forum — “fully working” (launch tier)

- [ ] Browse categories and topics
- [ ] Read thread; understand moderation delay
- [ ] Logged-in member can create topic and reply
- [ ] Staff can approve/reject in reasonable time
- [ ] Safety/disclaimer visible; no medical advice tone in UI

### 4.4 Hidden modules

- [ ] Guidance URL does not run triage API
- [ ] Products URL does not expose catalog
- [ ] Settings can re-enable later without redeploy

---

## 5. What we are **not** doing in pre-launch (unless you reprioritize)

| Item | Why defer |
|------|-----------|
| **Public products & pharmacies** | Coming soon at launch; admin prep only |
| **Product scraper API (PF)** | Future epic; fills catalog when ready |
| **AI symptom guidance (G)** | Legal gate; public coming soon anyway |
| **Sponsorships / paid placement (R4)** | Monetization after trust established |
| **Marketing CMS (R5)** | Legal pages exist statically |
| **Mobile apps (R8)** | Web-first |
| **Checkout / pharmacy integrations** | Out of product vision |
| **Visual triage rule builder** | Post-MVP admin nicety |

### PF — Future: product scraper + catalog (post-launch)

| ID | Work |
|----|------|
| PF.1 | Scraper API integration, mapping to `products` + pharmacy offers |
| PF.2 | Stale price handling, attribution, error monitoring |
| PF.3 | Enable `public_products` / `public_pharmacies` settings + remove coming soon |
| PF.4 | Meilisearch index for products/pharmacies |
| PF.5 | Public compare UX polish |

---

## 6. Mapping old roadmap labels → new plan

| Old | New home |
|-----|----------|
| R1 deploy, legal, Sentry | **P1**, **P9** |
| R2 Redis, queues, purge, runbooks | **P1** |
| R3 Meilisearch | **P2** |
| Track B backend | Mostly **done** → rolled into **P4** verification |
| Track W7 discovery | Mostly **done** |
| Track A Filament polish | **Done** (B-P3) + **P7** |
| MVP-1 permissions | **P7** if expanding |
| Registration (was R1 A1) | **P3** |
| Your forum ask | **P5** |
| Your coming soon ask | **P6** |
| Your emails ask | **P8** (detail later) |

---

## 7. Suggested immediate next steps (next 2–3 weeks)

P0 is locked. Suggested build order:

1. **P1.1–P1.2** — staging + Redis/queues (unblocks email, analytics jobs, search).
2. **P3** — site settings + registration (no invite-only workflow).
3. **P6** — coming soon for guidance + products + pharmacies + trim search/nav.
4. **P5** — forum overhaul (required before P7c community mods).
5. **P4** — doctors & facilities launch QA.
6. **P7b** — RBAC foundation + staff/client split (can start after P3).
7. **P7a** — analytics (can parallel once events schema agreed).
8. **P2** — Meilisearch (doctors, facilities, forum).
9. **P7c** — forum-scoped roles after P5.
10. **P8** — email waves before public launch.
11. **P9** — deploy.

**Approve dependency** for RBAC package (and analytics provider if not self-hosted) before P7b/P7a implementation.

---

## 8. Analytics — locked scope (P0)

| Layer | Included at launch |
|-------|-------------------|
| **Frontend traffic** | Yes — privacy-friendly analytics on public web |
| **Backend operational** | Yes — structured Filament dashboard (users, directory, community, search) |
| **Search term logging** | Recommended — aggregate `q` from search API for “what people look for” |

---

## 9. Permissions — locked scope (P0)

| Requirement | Plan |
|-------------|------|
| Permissions on **everything** in admin | P7b.2 — full permission map per Filament resource + settings |
| **Create roles** + assign permissions | P7b.6 — roles CRUD |
| **Staff vs clients** separate | P7b.4–P7b.5 |
| **Client forum moderators** | P7c — after forum done; global or per-category |
| **Not in launch** | Partner self-service editing their own clinic profile (unless added later as custom role) |

---

## 10. Email workshop (when you are ready)

Questions to answer before P8.5:

- Does a “doctor” have a notification email separate from facility contact?
- Do we email facility on every review or digest?
- MK-only templates at launch?
- Marketing opt-in?

Capture answers in a future `docs/email-notifications.md`.

---

## 11. Decision log

| Date | Decision |
|------|----------|
| 2026-05-16 | Pre-launch plan created; R1–R3 folded into pre-deploy phases P1–P2 |
| 2026-05-16 | **P0 locked:** no public products/pharmacies; blur + coming soon; scraper = epic PF |
| 2026-05-16 | **P0 locked:** analytics = frontend traffic + backend operational dashboard (structured) |
| 2026-05-16 | **P0 locked:** full RBAC; staff vs client users separate; forum mods per role/category after P5 |
| 2026-05-16 | Beta-closed / invite-only **retired** for planning |

---

## 12. How to use this with AI / engineering

Start a session with:

> Read `docs/pre-launch-master-plan.md` and `TASKS.md`. We are on phase **P_**_. Do not start deferred epics (G, R4, R5).

One phase per epic PR where possible.
