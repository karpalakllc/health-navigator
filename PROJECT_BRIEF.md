# Zdravje360 — Project brief

## Vision

Zdravje360 is a trusted, web-first medical platform for North Macedonia. It helps people find doctors and healthcare providers, compare pharmacy products and prices, read moderated reviews, participate in a health community forum, and access guided symptom information — with clear limits on what the platform can and cannot do medically.

Mobile applications will be added later using the same API; the backend remains the single source of truth.

## Audience and locale

- **Primary market:** North Macedonia.
- **Primary language at launch:** Macedonian (`mk`).
- **English:** Optional secondary language in a later phase; not required for Phase 0 documentation or scaffold work.

## Product capabilities (target)

These describe what the platform will offer. None of the following are implemented in the current scaffold.

### Directory and profiles

- Searchable doctor directory with specialties, contact details, and profile pages.
- Listing and detail pages for clinics, hospitals, laboratories, and pharmacies.

### Pharmacy

- Product catalog with prices across pharmacies where data is available.
- Clear sourcing and freshness expectations for price data (admin-managed).

### Reviews

- User-submitted reviews for doctors and facilities.
- **Admin approval** before publication; rejection and moderation workflows.

### Community forum

- Categories, topics, replies, and user profiles.
- Moderation tools, sticky topics, achievements/gamification, and anti-abuse measures.

### Symptom guidance (non-diagnostic)

- Symptom triage flow with **AI-assisted guidance** and prominent disclaimers.
- Must not present as diagnosis or emergency care; escalate to professional care where appropriate.

### Monetization and visibility

- Sponsored and featured placements for doctors, facilities, or products.
- Transparent labeling of paid placement vs organic results.

### Administration

- Full **CMS** for pages, banners, and editorial content.
- **Filament** admin panel on Laravel for operational staff.

## Technical direction

| Principle | Choice |
|-----------|--------|
| API ownership | Laravel (`apps/api`) owns data, validation, authorization, and side effects |
| Public UI | Next.js (`apps/web`) — Macedonian-first UX |
| Client ↔ API | **Direct API calls** from Next.js to Laravel by default; no BFF unless a documented exception |
| Search | Meilisearch for public search indexes; synced from Laravel |
| Data store | PostgreSQL |
| Performance | Redis for cache, sessions, and queues as needed |
| Admin | Filament inside `apps/api`, separate from public Next routes |

## Safety, compliance, and trust (policy-level)

This brief is not legal advice. Engineering and content must support:

- **Not medical advice:** Copy and flows state that content is informational; users must consult licensed professionals for diagnosis and treatment.
- **Emergency:** Triage flows must not delay emergency care; direct users to emergency services when appropriate.
- **Moderation:** Reviews and forum content are subject to approval and ongoing moderation.
- **Privacy:** Personal and health-related data handled according to applicable law (details in future privacy/security docs).
- **Transparency:** Sponsored content is clearly marked.

## Current state vs target

| Area | Shipped today | Next (roadmap) |
|------|---------------|----------------|
| **API** | Doctors, facilities, pharmacies, products, reviews, forum, rule-based symptom guidance (`/triage/*`), auth, rate limits | Registration/reset (R1), Redis/queues (R2), Meilisearch (R3), sponsorships (R4) |
| **Admin** | Filament: directories, reviews, forum, symptom guidance flow | Legal/CMS pages (R1 E1), sponsorship tools (R4), marketing CMS (R5) |
| **Web** | Directories, forum, login, reviews, SQL `/search` hub, `/guidance`, EN dev default | MK-first UI (R1 H1), legal pages (R1), staging/prod deploy (R1 D1) |
| **Search** | SQL `q` on list endpoints; thin search hub | Meilisearch indexes + unified search (R3) — **not beta** |
| **Guidance** | Rules-only Symptom guidance (3f-a); [triage-safety.md](./docs/triage-safety.md) | AI assist (G / 3f-b) — **gated, not beta** |
| **Accounts** | Staff-seeded members; login via cookie bridge | Public registration **preferred** before broad beta; invite-only beta **valid** (R1 A1) |
| **Infra** | CI; local Postgres; `infra/` placeholder | Staging/production baseline (R1 D1); Redis (R2) |
| **Mobile** | — | Same API v1 clients (R8) |

Planning detail: [docs/roadmap.md](./docs/roadmap.md) and [TASKS.md](./TASKS.md) (phases **R1–R8**, gated **G**).

## Non-goals (current engineering)

- **3f-b AI triage** until legal gate and triage-safety update (see TASKS **G**).
- Meilisearch, sponsorships, mobile apps — post-beta unless explicitly reprioritized.
- Checkout, cart, pharmacy stock sync, external pharmacy APIs.
- Diagnosis claims, emergency dispatch, clinician escalation in guidance flows.

## Definition of “foundation complete”

**Platform + domain foundations are in place** for beta prep:

- PostgreSQL, Sanctum API auth, roles, Filament admin, [API contract](./docs/api-contract.md).
- Public directories, moderated reviews and forum, pharmacy catalog, rule-based Symptom guidance (3f-a), public web shell and member UX (Phase 4).

**Beta-ready** is defined separately in [TASKS.md](./TASKS.md) (beta checklist + **R1**). Do not confuse “foundation complete” with “beta launched.”

## Related documents

- [README.md](./README.md) — entry point and local dev
- [docs/architecture.md](./docs/architecture.md) — system design
- [TASKS.md](./TASKS.md) — backlog and beta checklist (roadmap R1–R8)
- [docs/roadmap.md](./docs/roadmap.md) — one-page roadmap summary
