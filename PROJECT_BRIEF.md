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

| Area | Current (scaffold) | Target |
|------|-------------------|--------|
| API | Laravel 13 default app, SQLite/default DB in `.env.example` | PostgreSQL, Redis, versioned JSON API |
| Admin | Not installed | Filament CMS + moderation |
| Web | Next.js starter page | MK-localized product UI consuming API |
| Search | Not configured | Meilisearch indexes |
| Domains | No business models or routes | Modules per `TASKS.md` phases |

## Non-goals (this phase)

- Implementing auth, roles, or user registration flows.
- Doctor, facility, pharmacy, review, forum, or triage **features**.
- Installing Filament, Meilisearch client packages, or production infra (Docker, CI) — tracked in [TASKS.md](./TASKS.md) Phase 1+.
- Mobile app repositories or app-store releases.

## Definition of “foundation complete”

Foundation is ready to start domain work when:

1. Root and architecture docs match reality and decisions.
2. Local PostgreSQL, Redis, and Meilisearch are documented and runnable (Phase 1).
3. API has health check, CORS for web origin, and agreed API versioning prefix.
4. Auth approach is chosen and documented (Phase 2).
5. Filament is installed and reachable for staff (Phase 2).

Domain epics (doctors, pharmacy, forum, etc.) begin only after Phase 2 unless explicitly reprioritized.

## Related documents

- [README.md](./README.md) — entry point and local dev
- [docs/architecture.md](./docs/architecture.md) — system design
- [TASKS.md](./TASKS.md) — phased backlog
